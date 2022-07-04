<?php

namespace Elastico\Query\Builder;

use Elastico\Query\Response\Response;
use Generator;
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
                ->keyBy(fn ($hit) => $hit->get_id())
                ->all()
            ;

            while ($total) {
                $response = $this->getConnection()->performQuery('search', array_merge($payload, [
                    // 'scroll_id' => $response['_scroll_id'],
                    // 'scroll' => $seconds.'s',
                    'search_after' => $response['hits']['hits'][count($response['hits']['hits']) - 1]['sort'],
                ]));

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
                    ->keyBy(fn ($hit) => $hit->get_id())
                    ->all()
            ;
                // $total = count($response['hits']['hits']);
            }

            if (isset($response['_scroll_id'])) {
                // $this->getConnection()->performQuery('clearScroll', ['scroll_id' => $response['_scroll_id']]);

                $pit = $this->getConnection()->performQuery('closePointInTime', [
                    'body' => [''],
                ]);
            }
        });
    }

    public function enumerate(
        string $field,
        string $string = null,
        string $after = null,
        int $size = null,
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

                // response($response)->send();

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
