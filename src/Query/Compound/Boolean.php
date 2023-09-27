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

    const FILTER_TYPES = [
        'must',
        'should',
        'filter',
        'must_not',
    ];

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
        foreach (static::FILTER_TYPES as $type) {
            foreach ($this->{$type} as $query) {
                $payload[$type][] = $query;
            }
        }
        if (!is_null($this->minimum_should_match)) {
            $payload['minimum_should_match'] = $this->minimum_should_match;
        }

        if (!is_null($this->boost)) {
            $payload['boost'] = $this->boost;
        }


        if (count($payload) == 1) {
            if (
                !empty($payload['filter'][0])
                && count($payload['filter']) === 1
                && $payload['filter'][0] instanceof self
            ) {
                return $payload['filter'][0]->getPayload();
            }

            if (
                !empty($payload['must'][0])
                && count($payload['must']) === 1
                && $payload['must'][0] instanceof self
            ) {
                return $payload['must'][0]->getPayload();
            }

            if (
                !empty($payload['should'][0])
                && count($payload['should']) === 1
                && $payload['should'][0] instanceof self
            ) {
                return $payload['should'][0]->getPayload();
            }
        }

        foreach (static::FILTER_TYPES as $type) {
            if (!empty($payload[$type])) {
                $payload[$type] = collect($payload[$type])
                    ->map(fn ($query) => $query->compile())
                    ->all();
            }
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


    public function isEmpty(): bool
    {
        return empty($this->must) && empty($this->should) && empty($this->filter) && empty($this->must_not);
    }
}
