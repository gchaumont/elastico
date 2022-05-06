<?php

namespace Elastico\Query\Term;

use Elastico\Query\Query;

/**
 * Elastic Exists Query.
 */
class Wildcard extends Query
{
    protected $type = 'wildcard';

    protected ?int $boost = null;

    protected string $field;

    public function getPayload(): array
    {
        $payload = [
            $this->field => [
                'value' => $this->value,
            ],
        ];
        if (!is_null($this->boost)) {
            $payload[$this->field]['boost'] = $this->boost;
        }

        return $payload;
    }

    public function value(string $value): self
    {
        $this->value = $value;

        return $this;
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
