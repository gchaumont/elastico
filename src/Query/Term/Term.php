<?php

namespace Gchaumont\Query\Term;

use Gchaumont\Query\Query;
use RuntimeException;

/**
 * Elastic Term Query.
 */
class Term extends Query
{
    protected $type = 'term';

    protected string $field;

    protected string|int|float|bool $value;

    protected ?float $boost = null;

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

    public function value(string|int|float|bool $value): self
    {
        if (is_null($value)) {
            throw new RuntimeException('Empty Value passed to Term Query');
        }
        $this->value = $value;

        return $this;
    }

    public function boost(float $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
