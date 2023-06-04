<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * Max Aggregation.
 */
class Max extends Aggregation
{
    public const TYPE = 'max';

    public function __construct(
        public string $field,
        public null|array $script = null,
    ) {
    }

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
