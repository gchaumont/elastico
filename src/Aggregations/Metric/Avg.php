<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * Avg Aggregation.
 */
class Avg extends Aggregation
{
    public const TYPE = 'avg';

    public function __construct(
        public string $field
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
