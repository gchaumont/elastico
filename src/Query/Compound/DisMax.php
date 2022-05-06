<?php

namespace Gchaumont\Query\Compound;

use Gchaumont\Query\Query;

/**
 * Elasticsearch Disjuction Max Query.
 */
class DisMax extends Query
{
    protected string $type = 'dis_max';

    protected array $queries;

    protected ?float $tie_breaker = null;

    public function getPayload(): array
    {
        $payload = [];
        foreach ($this->queries as $query) {
            $payload['queries'][] = $query->compile();
        }
        if (!is_null($this->tie_breaker)) {
            $payload['tie_breaker'] = $this->tie_breaker;
        }

        return $payload;
    }

    public function query(Query $query): self
    {
        $this->queries[] = $query;

        return $this;
    }

    public function tieBreaker(float $tie_beaker): self
    {
        $this->tie_beaker = $tie_beaker;

        return $this;
    }
}
