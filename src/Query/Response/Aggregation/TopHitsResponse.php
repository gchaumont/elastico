<?php

namespace Elastico\Query\Response\Aggregation;

use Illuminate\Support\Collection;

/**
 *  Aggregation Response.
 */
class TopHitsResponse extends AggregationResponse implements \ArrayAccess
{
    public function hits(): Collection
    {
        $class = $this->aggregation->model;

        return (new $class())->hydrate($this->response()['hits']['hits']);
    }

    public function total(): int
    {
        return $this->response()['hits']['total']['value'];
    }
}
