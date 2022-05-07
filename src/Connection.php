<?php

namespace Elastico;

use DateTime;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Events\QueryExecuted;
use Elastico\Events\QueryStarted;
use Http\Promise\Promise;
use RuntimeException;

/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class Connection
{
    protected DateTime $_requestStartTime;

    protected string $_requestIdentifier;

    public function __construct(
        public readonly string $name,
        protected readonly Client $client,
    ) {
    }

    public function performQuery(string $method, array $payload): Promise|Elasticsearch
    {
        $identifier = $method.'.'.rand(0, 10000000);

        $this->startingQuery(endpoint: $method, identifier: $identifier);

        $response = $this->client->{$method}($payload);

        if ($response instanceof Promise) {
            $response->then(
                fn ($r) => $this->endingQuery(
                    method: $method,
                    payload: $payload,
                    response: json_decode((string) $r->getBody(), true),
                    identifier: $identifier
                ),
                fn ($r) => $this->endingQuery(
                    method: $method,
                    payload: $payload,
                    response: json_decode((string) $r->getBody(), true),
                    identifier: $identifier
                ),
            );
        } elseif ($response instanceof Elasticsearch) {
            $this->endingQuery(
                method: $method,
                payload: $payload,
                response: json_decode((string) $response->getBody(), true),
                identifier: $identifier
            );
        } else {
            throw new RuntimeException('Unrecognised Elastic Response', 1);
        }

        return $response;
    }

    private function startingQuery(string $endpoint, string $identifier): void
    {
        event(new QueryStarted(
            query_identifier: $identifier,
            query_name: $this->getRequestName($endpoint),
        ));
    }

    private function getRequestName(string $endpoint): string
    {
        return strtoupper($endpoint).' from '.implode(', ', $this->index ?? []);
    }

    private function endingQuery(
        string $method,
        array $payload,
        array $response,
        string $identifier
    ): void {
        // $operation =  match ($method) {
        //     'search' ,
        //     'scroll' ,
        //     'clearScroll' ,
        //     'msearch' => 'search',
        //     'get',
        //     'mget' => 'get',
        //     'updateByQuery' => 'update',
        //     'deleteByQuery' => 'delete',
        //     'count' => 'count',
        //     'bulk' => array_keys($payload['body']),
        // };
        // queries: match ($method) {
        //     'msearch' => count($payload['body']) / 2,
        //     'msearch' => count($payload['body']) / 2,
        //     default => 1,
        // }
        event(
            new QueryExecuted(
                query_identifier: $identifier,
                query_name: $this->getRequestName($method),
                status_code: 0, //static::getClient()::$lastStatusCode,
                affected_docs: match ($method) { // not response data or lose async
                    'search' => $response['hits']['total']['value'],
                    // 'msearch' => collect($response)
                    //     ->reduce(fn ($carry, $response) => $carry + $response['hits']['total']['value']),
                    'update' ,
                    'delete' ,
                    'updateByQuery' ,
                    'deleteByQuery' => $response['total'],
                    'mget' => count($payload['body']['docs']),
                    'bulk' => count($payload['body']) / 2,
                    'get' => 1,
                    default => 1,
                },
                indices: $this->index ?? [],
            )
        );
    }
}
