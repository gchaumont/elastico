<?php

namespace Elastico\Query;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Query\Response\PromiseResponse;
use Elastico\Query\Response\Response;
use GuzzleHttp\Promise\Promise;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Support\LazyCollection;

/**
 *  Elasticsearch Query Builder
 *  Extension of Larvel Database Query Builder.
 */
class Processor extends BaseProcessor
{
    /**
     * Process the results of a "select" query.
     *
     * @param array $results
     *
     * @return array
     */
    public function processSelect(BaseBuilder $query, $results)
    {
        return new Response(
            items: $results['hits']['hits'] ?? [],
            total: $results['hits']['total']['value'] ?? 0,
            aggregations: $results['aggregations'] ?? [],
            response: $this->resolvePromise($results),
            query: $query,
        );

        return new PromiseResponse(
            source: fn ($r): array => $r['hits']['hits'] ?? [],
            total: fn ($r): int => $r['hits']['total']['value'] ?? 0,
            aggregations: fn ($r): array => $r['aggregations'] ?? [],
            response: $results,
            query: $query,
        );
    }

    public function processFind(BaseBuilder $query, $results)
    {
        return (new Response(
            items: [$results],
            total: 1,
            aggregations: [],
            response: $this->resolvePromise($results),
            query: $query,
        ))->first();

        try {
            return (new PromiseResponse(
                source: fn ($r): array => [$r],
                total: fn ($r): int => 1,
                aggregations: fn ($r): array => [],
                response: $results,
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
    }

    public function processFindMany(BaseBuilder $query, $results)
    {
        return LazyCollection::make(function () use ($results) {
            yield from (new PromiseResponse(
                total: fn ($r): int => count($r['docs']),
                source: fn ($r): array => collect($r['docs'])
                    ->filter(fn ($d) => !empty($d['found']) && true === $d['found'])
                    ->keyBy(fn ($hit) => $hit['_id'])
                    ->all(),
                aggregations: fn ($r): array => [],
                response: $results,
            ))->hits();
        });
    }

    /**
     * Process an  "insert get ID" query.
     *
     * @param string      $sql
     * @param array       $values
     * @param null|string $sequence
     *
     * @return int
     */
    public function processInsertGetId(BaseBuilder $query, $sql, $values, $sequence = null)
    {
        throw new Exception('TODO', 1);
        $query->getConnection()->insert($sql, $values);

        $id = $query->getConnection()->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a column listing query.
     *
     * @param array $results
     *
     * @return array
     */
    public function processColumnListing($results)
    {
        return $results;
    }

    public function resolvePromise($response)
    {
        if ($response instanceof Promise) {
            return $response->wait()->asArray();
        }
        if ($response instanceof Elasticsearch) {
            return $response->asArray();
        }

        return $response;
    }
}
