<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * ValueCount Aggregation.
 */
class ValueCount extends Aggregation
{
    public const TYPE = 'value_count';

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
