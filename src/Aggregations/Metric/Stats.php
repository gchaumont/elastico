<?php

namespace Gchaumont\Aggregations\Metric;

use Gchaumont\Aggregations\Aggregation;

/**
 * Stats Aggregation.
 */
class Stats extends Aggregation
{
    public string $type = 'stats';

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
