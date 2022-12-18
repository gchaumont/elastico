<?php

namespace Elastico\Query\Builder;

use Elastico\Query\Query;
use Elastico\Query\Term\Range;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Terms;

trait HandlesFilters
{
    use HasWhere;

    protected array $filters = [];

    protected array $postFilters = [];

    public function postWhere(string $field, $operator = null, $value = null): self
    {
        if (2 == func_num_args()) {
            $value = $operator;
            $operator = '=';
        }
        if (is_null($value)) {
            throw new \Exception('Null value passed as filter');
        }

        $value = static::formatFilterValue($value);

        match ($operator) {
            '>' => $this->getPostFilter()->filter((new Range())->field($field)->gt($value)),
            '>=' => $this->getPostFilter()->filter((new Range())->field($field)->gte($value)),
            '<' => $this->getPostFilter()->filter((new Range())->field($field)->lt($value)),
            '<=' => $this->getPostFilter()->filter((new Range())->field($field)->lte($value)),
            '=' => match (is_array($value)) {
                true => $this->getPostFilter()->filter((new Terms())->field($field)->values($value)),
                false => $this->getPostFilter()->filter((new Term())->field($field)->value($value)),
            },
            default => throw new \InvalidArgumentException('Invalid where opterator')
        };

        return $this;
    }

    public function should(Query $query): static
    {
        $this->getQuery()->should($query);

        return $this;
    }

    public function mustNot(Query $query): static
    {
        $this->getQuery()->mustNot($query);

        return $this;
    }

    public function must(Query $query): static
    {
        $this->getQuery()->must($query);

        return $this;
    }

    public function filter(Query $query): static
    {
        $this->getQuery()->filter($query);

        return $this;
    }
}
