<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Auto Date Histogram Aggregation.
 */
class AutoDateHistogram extends BucketAggregation
{
    public const TYPE = 'auto_date_histogram';

    public function __construct(
        public string $field,
        public int $buckets,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'field' => $this->field,
            'buckets' => $this->buckets,
        ];
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function buckets(int $buckets): self
    {
        $this->buckets = $buckets;

        return $this;
    }
}
