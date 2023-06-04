<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * Stats Aggregation.
 */
class ExtendedStats extends Aggregation
{
    public const TYPE = 'extended_stats';

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
