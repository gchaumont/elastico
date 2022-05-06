<?php

namespace Elastico\Query\Term;

use Elastico\Query\Query;

/**
 * Elastic Range Query.
 */
class Range extends Query
{
    protected $type = 'range';

    protected string $field;

    protected $gt;

    protected $gte;

    protected $lt;

    protected $lte;

    protected ?float $boost = null;

    public function getPayload(): array
    {
        $payload = [
            $this->field => [
                // 'gt' => $this->gt ?? null,
                // 'gte' => $this->gte
            ],
        ];
        foreach (['gt', 'gte', 'lt', 'lte', 'boost'] as $property) {
            if (!is_null($this->{$property})) {
                $payload[$this->field][$property] = $this->{$property};
            }
        }

        return $payload;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function gt($gt): self
    {
        $this->gt = $gt;

        return $this;
    }

    public function gte($gte): self
    {
        $this->gte = $gte;

        return $this;
    }

    public function lt($lt): self
    {
        $this->lt = $lt;

        return $this;
    }

    public function lte($lte): self
    {
        $this->lte = $lte;

        return $this;
    }

    public function boost(float $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
