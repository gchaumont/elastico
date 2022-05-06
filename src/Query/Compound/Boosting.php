<?php

namespace Gchaumont\Query\Compound;

use Gchaumont\Query\Query;

/**
 * Elasticsearch Boosting Query.
 */
class Boosting extends Query
{
    protected string $type = 'boosting';

    protected array

 $positive = [];

    protected array

 $negative = [];

    protected float $negative_boost;

    public function getPayload(): array
    {
        $payload = [];
        foreach ($this->positive as $query) {
            $payload['positive'][] = $query->compile();
        }
        foreach ($this->negative as $query) {
            $payload['negative'][] = $query->compile();
        }
        if (!is_null($this->negative_boost)) {
            $payload['negative_boost'] = $this->negative_boost;
        }

        return $payload;
    }

    public function positive(Query $positive): self
    {
        $this->positive[] = $positive;

        return $this;
    }

    public function negative(Query $negative): self
    {
        $this->negative[] = $negative;

        return $this;
    }

    public function negativeBoost(float $negative_boost): self
    {
        $this->negative_boost = $negative_boost;

        return $this;
    }
}
