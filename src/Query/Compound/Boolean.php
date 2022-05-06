<?php

namespace Elastico\Query\Compound;

use Elastico\Query\Builder\HasWhere;
use Elastico\Query\Query;

/**
 * Elasticsearch Boolean Query.
 */
class Boolean extends Query
{
    use HasWhere;

    protected string $type = 'bool';

    protected array $must = [];

    protected array $should = [];

    protected array $filter = [];

    protected array $must_not = [];

    protected ?int $minimum_should_match = null;

    protected ?float $boost = null;

    public function getPayload(): array
    {
        $payload = [];
        foreach (['must', 'should', 'filter', 'must_not'] as $type) {
            foreach ($this->{$type} as $query) {
                $payload[$type][] = $query->compile();
            }
        }
        if (!is_null($this->minimum_should_match)) {
            $payload['minimum_should_match'] = $this->minimum_should_match;
        }

        if (!is_null($this->boost)) {
            $payload['boost'] = $this->boost;
        }

        return $payload;
    }

    public function must(Query $must): self
    {
        $this->must[] = $must;

        return $this;
    }

    public function should(Query $should): self
    {
        $this->should[] = $should;

        return $this;
    }

    public function filter(Query $filter): self
    {
        $this->filter[] = $filter;

        return $this;
    }

    public function mustNot(Query $must_not): self
    {
        $this->must_not[] = $must_not;

        return $this;
    }

    public function min(int $min): self
    {
        $this->minimum_should_match = $min;

        return $this;
    }

    public function boost(float $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
