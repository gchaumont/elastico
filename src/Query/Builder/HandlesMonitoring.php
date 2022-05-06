<?php

namespace Gchaumont\Query\Builder;

use DateTime;
use Gchaumont\Events\QueryExecuted;
use Gchaumont\Events\QueryStarted;

trait HandlesMonitoring
{
    protected DateTime $_requestStartTime;

    protected string $_requestIdentifier;

    private function startingQuery(string $endpoint): void
    {
        $this->_requestStartTime = new DateTime();
        $this->_requestIdentifier = $endpoint.'.'.rand(0, 10000000);

        event(new QueryStarted(
            query_identifier: $this->_requestIdentifier,
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
                query_identifier: $this->_requestIdentifier,
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
                indices: $this->index,
            )
        );
    }
}
