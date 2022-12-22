<?php

namespace Elastico\Query\Builder;

use Elastico\Eloquent\Model;
use Elastico\Query\Compound\Boolean;
use Elastico\Query\Query;
use Elastico\Query\Term\Range;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Terms;
use Illuminate\Support\Collection;

trait HasPostFilter
{
    public null|Query $post_filter = null;

    public function getPostFilter(): ?Query
    {
        return $this->post_filter ??= new Boolean();
    }

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

    public static function formatFilterValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $value = array_values($value);
        }

        return match (is_object($value)) {
            false => $value ,
            true => match (true) {
                $value instanceof Model => $value->getKey(),
                $value instanceof \DateTime => $value->format(\DateTime::ATOM),
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \UnitEnum => $value->name,
                $value instanceof \Stringable => (string) $value,
                default => $value,
            }
        };
    }
}
