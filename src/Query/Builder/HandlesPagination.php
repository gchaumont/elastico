<?php

namespace Elastico\Query\Builder;

use Elastico\Models\Model;
use Elastico\Query\Response\PromiseResponse;
use Generator;
use Http\Promise\Promise;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

trait HandlesPagination
{
    protected int $skip = 0;

    protected ?int $take = null;

    public function take(int $count): self
    {
        $this->take = $count;

        return $this;
    }

    public function skip(int $count): self
    {
        $this->skip = $count;

        return $this;
    }

    public function scroll(int $size = 500, int $seconds = 10): LazyCollection
    {
        return LazyCollection::make(function () use ($size, $seconds): Generator {
            $total = null;

            $payload = $this->buildPayload();
            // $payload['scroll'] = $seconds.'s';
            $payload['body']['size'] = $size;
            $payload['body']['sort'] = '_shard_doc';

            $pit = $this->getConnection()->performQuery('openPointInTime', [
                'index' => $payload['index'],
                'keep_alive' => $seconds.'s',
            ]);

            if ($pit instanceof Promise) {
                $pit = $pit->wait()->asArray();
            }

            $pit['keep_alive'] = $seconds.'s';

            $payload['body']['pit'] = $pit;
            unset($payload['index']);

            $response = $this->getConnection()->performQuery('search', $payload);

            yield from (new PromiseResponse(
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
                ->keyBy(fn ($hit) => $hit instanceof Model ? $hit->get_id() : $hit['_id'])
                ->all()
            ;
            if ($response instanceof Promise) {
                $response = $response->wait()->asArray();
            }

            while ($total) {
                $payload['body']['pit']['id'] = $response['pit_id'];
                $payload['body']['search_after'] = $response['hits']['hits'][count($response['hits']['hits']) - 1]['sort'];

                $response = $this->getConnection()->performQuery('search', $payload);

                if ($response instanceof Promise) {
                    $response = $response->wait()->asArray();
                }

                yield from (new PromiseResponse(
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
                    ->keyBy(fn ($hit) => $hit instanceof Model ? $hit->get_id() : $hit['_id'])
                    ->all()
            ;
                // $total = count($response['hits']['hits']);
            }

            if (isset($response['pit_id'])) {
                // $this->getConnection()->performQuery('clearScroll', ['scroll_id' => $response['_scroll_id']]);

                $this->getConnection()->performQuery('closePointInTime', [
                    'body' => ['id' => $response['pit_id']],
                ]);
            }
        });
    }

    public function enumerate(
        string $field,
        string $string = null,
        string $after = null,
        int $size = 10,
        bool $insensitive = true
    ): LazyCollection {
        return LazyCollection::make(function () use ($field, $string, $after, $size, $insensitive): Generator {
            do {
                $response = $this->getConnection()->performQuery('termsEnum', [
                    'index' => $this->buildPayload()['index'],
                    'body' => array_filter([
                        'field' => $field,
                        'size' => $size,
                        'string' => $string,
                        'search_after' => $after,
                        'case_insensitive' => $insensitive,
                    ]),
                ]);

                if ($response instanceof Promise) {
                    $response = $response->wait()->asArray();
                }

                yield from $response['terms'];

                $after = end($response['terms']);
            } while ($size == count($response['terms']));
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
}
