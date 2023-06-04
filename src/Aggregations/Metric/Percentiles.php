<?php

namespace Elastico\Aggregations\Metric;

use Elastico\Aggregations\Aggregation;

/**
 * Percentiles Aggregation.
 */
class Percentiles extends Aggregation
{
    public const TYPE = 'percentiles';

    public function __construct(
        public string $field,
        public null|array $percents = null,
        public null|bool $keyed = null,
    ) {
    }

    public function getPayload(): array
    {
        $payload = [
            'field' => $this->field,
        ];
        if (!is_null($this->percents)) {
            $payload['percents'] = $this->percents;
        }
        if (!is_null($this->keyed)) {
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
