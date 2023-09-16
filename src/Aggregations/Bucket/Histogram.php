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
        public null|int $min_doc_count = null,
    ) {
        # code...
    }

    public function getPayload(): array
    {
        return array_filter([
            'field' => $this->field,
            'interval' => $this->interval,
            'min_doc_count' => $this->min_doc_count,
        ]);
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
