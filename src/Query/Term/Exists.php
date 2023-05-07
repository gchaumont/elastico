<?php

namespace Elastico\Query\Term;

use Elastico\Query\Query;

/**
 * Elastic Exists Query.
 */
class Exists extends Query
{
    protected string $type = 'exists';

    protected ?int $boost = null;

    protected string $field;

    public function getPayload(): array
    {
        return array_filter([
            'field' => $this->field,
            'boost' => $this->boost,
        ]);
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function boost(int $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
