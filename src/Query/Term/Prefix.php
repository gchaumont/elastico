<?php

namespace Elastico\Query\Term;

use Elastico\Query\Query;

/**
 * Elastic Prefix Query.
 */
class Prefix extends Query
{
    protected string $type = 'prefix';

    protected ?float $boost = null;

    public function __construct(
        protected string $field,
        protected $value,
        float $boost = null
    ) {
        if (!is_null($boost)) {
            $this->boost($boost);
        }
    }

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

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function value($value): self
    {
        $this->value = $value;

        return $this;
    }

    public function boost(float $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
