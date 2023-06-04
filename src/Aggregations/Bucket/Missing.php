<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Sum Aggregation.
 */
class Missing extends BucketAggregation
{
    public const TYPE = 'missing';

    public function __construct(
        public string $field,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'field' => $this->field,
        ];
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }
}
