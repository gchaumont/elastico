<?php

namespace Gchaumont\Query\Compound;

use Gchaumont\Query\Query;

/**
 * Elasticsearch Constant Score Query.
 */
class ConstantScore extends Query
{
    protected string $type = 'constant_score';

    protected array

 $filter;

    protected ?float $boost = null;

    public function getPayload(): array
    {
        $payload = [];
        foreach ($this->filter as $query) {
            $payload['filter'][] = $query->compile();
        }
        if (!is_null($this->boost)) {
            $payload['boost'] = $this->boost;
        }

        return $payload;
    }

    public function filter(Query $filter): self
    {
        $this->filter[] = $filter;

        return $this;
    }

    public function boost(float $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
