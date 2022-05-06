<?php

namespace Gchaumont\Query\Builder;

use Gchaumont\Exceptions\ModelNotFoundException;
use Gchaumont\Models\Model;
use Gchaumont\Query\Response\Response;
use Generator;
use GuzzleHttp\Ring\Future\FutureArray;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

trait PerformsQuery
{
    protected $response;

    public function find(string $id): null|Model
    {
        try {
            return (new Response(
                hits: fn ($r): array => [$r],
                total: fn ($r): int => 1,
                aggregations: fn ($r): array => [],
                query: $this,
                response: $this->performQuery('get', [
                    'index' => $this->index,
                    'id' => $id,
                    '_source_includes' => implode(',', $this->source),
                ]),
            ))
                ->hits()
                ->first()
            ;
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            return null;
        }
    }

    public function findMany(iterable $ids): LazyCollection
    {
        $ids = collect($ids)->values();

        if ($ids->isEmpty()) {
            return new LazyCollection();
        }

        $response = $this->performQuery('mget', [
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
                hits: fn ($r): array => collect($r['docs'])
                    ->filter(fn ($d) => true === $d['found'])
                    ->keyBy(fn ($hit) => $hit['_id'])
                    ->all(),
                total: fn ($r): int => count($r['docs']),
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
            hits: fn ($r): array => $r['hits']['hits'] ?? [],
            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
            aggregations: fn ($r): array => $r['aggregations'] ?? [],
            response: $this->performQuery('search', $this->buildPayload()),
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
                            hits: fn ($r): array => $r['hits']['hits'] ?? [],
                            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
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
            $response = $this->performQuery('search', $payload);

            yield from (new Response(
                hits: fn ($r): array => $r['hits']['hits'],
                total: fn ($r): int => count($r['hits']['total']),
                aggregations: fn ($r): array => [],
                query: $this,
                response: $response,
            ))
                ->hits()
                ->tap(function ($hits) use (&$total) {
                    $total = $hits->count();
                })
                ->all()
            ;

            while ($total) {
                $response = $this->performQuery('scroll', [
                    'scroll_id' => $response['_scroll_id'],
                    'scroll' => $seconds.'s',
                ]);
                yield from (new Response(
                    hits: fn ($r): array => $r['hits']['hits'],
                    total: fn ($r): int => count($r['hits']['total']),
                    aggregations: fn ($r): array => [],
                    query: $this,
                    response: $response
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
                $this->performQuery('clearScroll', ['scroll_id' => $response['_scroll_id']]);
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

        return $this->performQuery('updateByQuery', $payload);
    }

    public static function bulk(array $payload): array|FutureArray
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
        $response = $this->performQuery('deleteByQuery', array_merge(
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

        $response = $this->performQuery('updateByQuery', $payload);

        if (!empty($response['failures'])) {
            throw new \Exception('Update by Query errors ', json_encode($response['failures']));
        }

        return $response;
    }

    public function count(): int
    {
        return $this->performQuery('count', $this->buildPayload())['count'];
    }

    private function performQuery(string $method, array $payload): mixed
    {
        $this->startingQuery(endpoint: $method);

        $payload['client']['future'] = 'async';

        $response = static::getClient()->{$method}($payload);

        if ($response instanceof FutureArray) {
            $response->then(
                fn ($r) => $this->endingQuery(method: $method, payload: $payload, response: $r),
                fn ($r) => $this->endingQuery(method: $method, payload: $payload, response: $r),
            );
        } else {
            $this->endingQuery(method: $method, payload: $payload, response: $response);
        }

        return $response;
    }
}
