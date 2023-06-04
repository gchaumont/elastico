<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * Sum Aggregation.
 */
class Sum extends Aggregation
{
    public const TYPE = 'sum';

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
