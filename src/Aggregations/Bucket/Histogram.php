<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Histogram Aggregation.
 */
class Histogram extends BucketAggregation
{
    public const TYPE = 'histogram';

    public function __construct(
        public string $field,
        public int|float $interval,
    ) {
        # code...
    }

    public function getPayload(): array
    {
        return [
            'field' => $this->field,
            'interval' => $this->interval,
        ];
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function interval(int $interval): self
    {
        $this->interval = $interval;

        return $this;
    }
}
