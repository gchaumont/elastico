<?php

namespace Gchaumont\Aggregations\Metric;

use Gchaumont\Aggregations\Aggregation;

/**
 * Min Aggregation.
 */
class Min extends Aggregation
{
    public string $type = 'min';

    public string $field;

    public array $script;

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
        ];
        if (!empty($this->script)) {
            $agg['script'] = $this->script;
        }

        return $agg;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function script(array $script): self
    {
        $this->script = $script;

        return $this;
    }
}
