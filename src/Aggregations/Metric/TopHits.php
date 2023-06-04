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

    public const TYPE = 'top_hits';

    public function __construct(
        public null|int $size = null,
        public null|int $from = 0,
        public null|string $model = null,
        public null|array $sort = null,
        public null|array $_source = null,

    ) {
    }

    public function getPayload(): array
    {
        $payload = [
            'from' => $this->from,
        ];

        if ($this->size !== null) {
            $payload['size'] = $this->size;
        }
        if (!empty($this->_source)) {
            $payload['_source'] = $this->_source;
        }
        if (!empty($this->sort)) {
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
