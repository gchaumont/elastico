<?php

namespace Gchaumont\Aggregations\Metric;

use Gchaumont\Aggregations\Aggregation;

/**
 * Sum Aggregation.
 */
class Sum extends Aggregation
{
    public string $type = 'sum';

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
