<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Histogram Aggregation.
 */
class VariableWidthHistogram extends BucketAggregation
{
    public string $type = 'variable_width_histogram';

    public string $field;

    public int $buckets;

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
