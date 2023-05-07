<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * Percentiles Aggregation.
 */
class Percentiles extends Aggregation
{
    public string $type = 'percentiles';

    public string $field;

    public array $percents;

    public bool $keyed;

    public function getPayload(): array
    {
        $payload = [
            'field' => $this->field,
        ];
        if (isset($this->percents)) {
            $payload['percents'] = $this->percents;
        }
        if (isset($this->keyed)) {
            $payload['keyed'] = $this->keyed;
        }

        return $payload;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function percents(array $percents): self
    {
        $this->percents = $percents;

        return $this;
    }

    public function keyed(bool $keyed): self
    {
        $this->keyed = $keyed;

        return $this;
    }
}
