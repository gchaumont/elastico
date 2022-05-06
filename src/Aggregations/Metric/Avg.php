<?php

namespace Gchaumont\Aggregations\Metric;

use Gchaumont\Aggregations\Aggregation;

/**
 * Avg Aggregation.
 */
class Avg extends Aggregation
{
    public string $type = 'avg';

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
