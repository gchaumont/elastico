<?php

namespace Elastico\Query\Term;

use Elastico\Query\Query;
use RuntimeException;

/**
 * Elastic Terms Query.
 */
class Terms extends Query
{
    protected string $type = 'terms';

    protected ?float $boost = null;

    public function __construct(
        protected string $field,
        protected array $values
    ) {
        if (empty($values)) {
            throw new RuntimeException('Empty Values passed to Terms Query');
        }
    }

    public function getPayload(): array
    {
        return array_filter([
            $this->field => $this->values,
            'boost' => $this->boost,
        ]);
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function values(array $values): self
    {
        if (!array_filter($values, fn ($v) => !is_null($v))) {
            throw new RuntimeException('Empty Values passed to Terms Query');
        }

        $this->values = $values;

        return $this;
    }

    public function boost(float $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
