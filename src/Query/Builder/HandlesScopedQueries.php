<?php

namespace Elastico\Query\Builder;

use Elastico\Exceptions\ModelNotFoundException;
use Elastico\Models\Model;
use Elastico\Query\Response\Response;
use Generator;
use Http\Promise\Promise;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

trait HandlesScopedQueries
{
    public function find(string $id): null|Model
    {
        try {
            return (new Response(
                total: fn ($r): int => 1,
                hits: fn ($r): array => [$r],
                aggregations: fn ($r): array => [],
                response: $this->getConnection()->performQuery('get', [
                    'index' => $this->index,
                    'id' => $id,
                    '_source_includes' => implode(',', $this->source),
                ]),
                query: $this,
            ))
                ->hits()
                ->first()
            ;
        } catch (\Elastic\Transport\Exception\NotFoundException $e) {
            return null;
        }
    }

    public function findMany(iterable $ids): LazyCollection
    {
        $ids = collect($ids)->values();

        if ($ids->isEmpty()) {
            return new LazyCollection();
        }

        $response = $this->getConnection()->performQuery('mget', [
            'body' => [
                'docs' => $ids
                    ->map(fn (string|int $id) => array_filter([
                        '_index' => $this->index,
                        '_id' => $id,
                        '_source' => $this->source ?? null,
                    ]))
                    ->values()
                    ->all(),
            ],
        ]);

        return LazyCollection::make(function () use ($response) {
            yield from (new Response(
                total: fn ($r): int => count($r['docs']),
                hits: fn ($r): array => collect($r['docs'])
                    ->filter(fn ($d) => true === $d['found'])
                    ->keyBy(fn ($hit) => $hit['_id'])
                    ->all(),
                aggregations: fn ($r): array => [],
                response: $response,
                query: $this,
            ))->hits();
        });
    }

    public function findOrFail(string $id): Model
    {
        return $this->find($id) ?? throw new ModelNotFoundException("Model not found: {$id}", 1);
    }

    public function get(): Response
    {
        return new Response(
            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
            hits: fn ($r): array => $r['hits']['hits'] ?? [],
            aggregations: fn ($r): array => $r['aggregations'] ?? [],
            response: $this->getConnection()->performQuery('search', $this->buildPayload()),
            query: $this
        );
    }

    public static function getMany(iterable $queries): Collection|LazyCollection
    {
        $queries = collect($queries);
        if ($queries->isEmpty()) {
            return collect();
        }
        $metaQuery = new static();
        $metaQuery->index = $queries
            ->map(fn ($q) => $q->index)
            ->collapse()
            ->unique()
            ->values()
            ->all()
        ;

        $response = $metaQuery->performQuery('msearch', ['body' => $queries
            ->map(fn ($query) => $query->buildPayload())
            ->flatMap(fn ($payload) => [
                ['index' => $payload['index']],
                $payload['body'],
            ])
            ->all(),
        ]);

        return LazyCollection::make(function () use ($response, $queries) {
            return $queries
                ->keys()
                ->combine(
                    $queries->values()
                        ->map(fn ($query, $i) => new Response(
                            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
                            hits: fn ($r): array => $r['hits']['hits'] ?? [],
                            aggregations: fn ($r): array => $r['aggregations'] ?? [],
                            response: $response['responses'][$i],
                            query: $query
                        ))
                )
            ;
        });
    }

    public function paginate(int $page = null, int $perPage = 10): LengthAwarePaginator
    {
        $page ??= request('page', 1);

        $response = $this->take($perPage)
            ->skip($perPage * ($page - 1))
            ->get()
        ;

        return new LengthAwarePaginator(
            items: $response->hits(),
            total: min(10000, $response->total()),
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => '/'.request()->path()]
        );
    }

    public function first(): null|Model
    {
        return $this->take(1)->get()->hits()->first();
    }

    public function scroll(int $size = 500, int $seconds = 10): LazyCollection
    {
        return LazyCollection::make(function () use ($size, $seconds): Generator {
            $total = null;

            $payload = $this->buildPayload();
            $payload['scroll'] = $seconds.'s';
            $payload['body']['size'] = $size;
            $response = $this->getConnection()->performQuery('search', $payload);

            yield from (new Response(
                total: fn ($r): int => count($r['hits']['total']),
                hits: fn ($r): array => $r['hits']['hits'],
                aggregations: fn ($r): array => [],
                response: $response,
                query: $this,
            ))
                ->hits()
                ->tap(function ($hits) use (&$total) {
                    $total = $hits->count();
                })
                ->all()
            ;

            while ($total) {
                $response = $this->getConnection()->performQuery('scroll', [
                    'scroll_id' => $response['_scroll_id'],
                    'scroll' => $seconds.'s',
                ]);
                yield from (new Response(
                    total: fn ($r): int => count($r['hits']['total']),
                    hits: fn ($r): array => $r['hits']['hits'],
                    aggregations: fn ($r): array => [],
                    response: $response,
                    query: $this
                ))
                    ->hits()
                    ->tap(function ($hits) use (&$total) {
                        $total = $hits->count();
                    })
                    ->all()
            ;
                // $total = count($response['hits']['hits']);
            }

            if (isset($response['_scroll_id'])) {
                $this->getConnection()->performQuery('clearScroll', ['scroll_id' => $response['_scroll_id']]);
            }
        });
    }

    public function updateByQuery(array $script, string $conflicts = null)
    {
        $payload = $this->buildPayload();
        if (!empty($conflicts)) {
            $payload['conflicts'] = $conflicts;
        }

        $payload['slices'] = 'auto';
        $payload['body']['script'] = $script;

        return $this->getConnection()->performQuery('updateByQuery', $payload);
    }

    public static function bulk(array $payload): array|Promise
    {
        if (empty($payload)) {
            return [];
        }
        $static = new static();
        $static->index(
            collect($payload['body'])
                ->filter(fn ($item, $k) => 0 == $k % 2)
                ->map(fn ($item) => collect($item)->first()['_index'])
                ->unique()
                ->values()
                ->all()
        );

        $response = $static->performQuery('bulk', $payload);

        if (true == $response['errors']) {
            $errors = collect($response['items'])
                ->map(fn ($item) => [
                    'action' => collect($item)->keys()->first(),
                    ...collect($item)->first(),
                ])
                ->reject(fn ($item) => empty($item['error']))
            ;

            throw new \Exception('Bulk  errors '.json_encode($errors->all()));
        }

        return $response;
    }

    public function delete()
    {
        $response = $this->getConnection()->performQuery('deleteByQuery', array_merge(
            $this->buildPayload(),
            [
                'slices' => 'auto',
                'conflicts' => 'proceed',
            ]
        ));

        if (!empty($response['failures'])) {
            throw new \Exception('Delete by Query errors ', json_encode($response['failures']));
        }

        return $response;
    }

    public function update(array $data, bool $proceed = null)
    {
        $script = '';
        foreach ($data as $key => $value) {
            $script .= "ctx._source.{$key} = params.{$key};\n";
        }

        $payload = $this->buildPayload();

        $payload['slices'] = 'auto';
        if (true === $proceed) {
            $payload['conflicts'] = 'proceed';
        }
        $payload['body']['script']['source'] = $script;
        $payload['body']['script']['params'] = $data;

        $response = $this->getConnection()->performQuery(method: 'updateByQuery', payload: $payload);

        if (!empty($response['failures'])) {
            throw new \Exception('Update by Query errors ', json_encode($response['failures']));
        }

        return $response;
    }

    public function count(): int
    {
        return $this->getConnection()->performQuery(method: 'count', payload: $this->buildPayload())['count'];
    }
}
