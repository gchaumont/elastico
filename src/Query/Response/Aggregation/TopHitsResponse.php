<?php

namespace Elastico\Query\Response\Aggregation;

use ArrayAccess;
use Elastico\Aggregations\Aggregation;
use Elastico\Query\Response\Response;

/**
 *  Aggregation Response.
 */
class TopHitsResponse extends AggregationResponse implements ArrayAccess
{
    public function getResponse(): Response
    {
        return new Response(
            hits: fn ($data): array => $data['hits']['hits'],
            total: fn ($data): int => $data['hits']['total']['value'],
            aggregations: fn (): array => [],
            response: $this->response(),
            model: $this->aggregation->model,
        );
    }

    public function hits()
    {
        return $this->getResponse()->hits();
    }

    public function total()
    {
        return $this->getResponse()->total();
    }
}
