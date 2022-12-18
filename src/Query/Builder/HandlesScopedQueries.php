<?php

namespace Elastico\Query\Builder;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastico\Exceptions\ModelNotFoundException;
use Elastico\Models\Model;
use Elastico\Query\Response\PromiseResponse;
use Elastico\Query\Response\Response;
use Http\Promise\Promise;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

trait HandlesScopedQueries
{
    public function find(string $id): null|Model
    {
        try {
            return (new PromiseResponse(
                source: fn ($r): array => [$r],
                total: fn ($r): int => 1,
                aggregations: fn ($r): array => [],
                response: $this->getConnection()->performQuery('get', array_filter([
                    'index' => $this->index,
                    'id' => $id,
                    '_source_includes' => implode(',', $this->source),
                ])),
                query: $this,
            ))
                ->hits()
                ->first()
            ;

            // } catch (\Elastic\Transport\Exception\NotFoundException $e) {
        //     return null;
        } catch (ClientResponseException $e) {
            if ('404' == $e->getResponse()->getStatusCode()) {
                return null;
            }
        }
        // catch (\Http\Client\Exception\TransferException $e) {

        // }
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
            yield from (new PromiseResponse(
                source: fn ($r): array => collect($r['docs'])
                    ->filter(fn ($d) => !empty($d['found']) && true === $d['found'])
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
        return new PromiseResponse(
            source: fn ($r): array => $r['hits']['hits'] ?? [],
            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
            aggregations: fn ($r): array => $r['aggregations'] ?? [],
            response: $this->getConnection()->performQuery('search', $this->buildPayload()),
            query: $this
        );
    }

    public function getMany(iterable $queries): Collection|LazyCollection
    {
        $queries = collect($queries);
        if ($queries->isEmpty()) {
            return collect();
        }

        $this->index($queries
            ->map(fn ($q) => $q->index)
            ->collapse()
            ->unique()
            ->values()
            ->all());

        $response = $this->getConnection()->performQuery('msearch', ['body' => $queries
            ->map(fn ($query) => $query->buildPayload())
            ->flatMap(fn ($payload) => [
                ['index' => $payload['index']],
                $payload['body'],
            ])
            ->all(),
        ]);

        return LazyCollection::make(function () use ($response, $queries) {
            if ($response instanceof Promise) {
                $response = $response->wait()->asArray();
            }

            return $queries
                ->keys()
                ->combine(
                    $queries->values()
                        ->map(fn ($query, $i) => new PromiseResponse(
                            source: fn ($r): array => $r['hits']['hits'] ?? [],
                            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
                            aggregations: fn ($r): array => $r['aggregations'] ?? [],
                            response: $response['responses'][$i],
                            query: $query
                        ))
                )
            ;
        });
    }

    public function first(): null|Model
    {
        return $this->take(1)->get()->hits()->first();
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

    public function bulk(array $payload): array|Promise
    {
        if (empty($payload)) {
            return [];
        }
        // $static = new static();
        $this->index(
            collect($payload['body'])
                ->filter(fn ($item, $k) => 0 == $k % 2)
                ->map(fn ($item) => collect($item)->first()['_index'])
                ->unique()
                ->values()
                ->all()
        );

        $response = $this->getConnection()->performQuery('bulk', $payload);
        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }
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
        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }

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
        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }
        if (!empty($response['failures'])) {
            throw new \Exception('Update by Query errors ', json_encode($response['failures']));
        }

        return $response;
    }

    public function count(): int
    {
        $response = $this->getConnection()->performQuery(method: 'count', payload: $this->buildPayload());
        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }

        return $response['count'];
    }

    public function exists(): bool
    {
        $payload = $this->take(0)->buildPayload();
        $payload['terminate_after'] = 1;

        $response = $this->getConnection()->performQuery(method: 'search', payload: $payload);

        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }

        return $response['hits']['total']['value'] > 0;
    }
}
