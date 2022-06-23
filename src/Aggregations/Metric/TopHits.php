<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;
use Elastico\Query\Response\Aggregation\TopHitsResponse;

/**
 * TopHits Aggregation.
 */
class TopHits extends Aggregation
{
    const RESPONSE_CLASS = TopHitsResponse::class;

    public string $type = 'top_hits';

    public array $_source;

    public array $sort;

    public int $size;

    public int $from = 0;

    public string $model;

    public function getPayload(): array
    {
        $payload = [
            'from' => $this->from,
            'size' => $this->size,
        ];
        if (isset($this->_source)) {
            $payload['_source'] = $this->_source;
        }
        if (isset($this->sort)) {
            $payload['sort'] = $this->sort;
        }

        return $payload;
    }

    public function from(int $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function sort(array $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function source(array $source): self
    {
        $this->_source = $source;

        return $this;
    }
}
