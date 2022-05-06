<?php

namespace Gchaumont\Aggregations\Bucket;

use Gchaumont\Aggregations\Aggregation;

/**
 * Histogram Aggregation.
 */
class Histogram extends BucketAggregation
{
    public string $type = 'histogram';

    public string $field;

    public int $interval;

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
