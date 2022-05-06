<?php

namespace Gchaumont\Aggregations\Metric;

use Gchaumont\Aggregations\Aggregation;

/**
 * ValueCount Aggregation.
 */
class ValueCount extends Aggregation
{
    public string $type = 'value_count';

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
