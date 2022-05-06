<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Range Aggregation.
 */
class Range extends BucketAggregation
{
    public string $type = 'range';

    public string $field;

    public array $ranges;

    public bool $keyed;

    public int $size = 10;

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
            'ranges' => $this->ranges,
        ];

        if (true == ($this->keyed ?? null)) {
            $agg['keyed'] = true;
        }

        return $agg;
    }

    public function ranges(array $ranges): self
    {
        $this->ranges = $ranges;

        return $this;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function keyed(bool $keyed): self
    {
        $this->keyed = $keyed;

        return $this;
    }
}
