<?php

namespace Gchaumont\Aggregations\Metric;

use Gchaumont\Aggregations\Aggregation;

/**
 * Stats Aggregation.
 */
class ExtendedStats extends Aggregation
{
    public string $type = 'extended_stats';

    public string $field;

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
