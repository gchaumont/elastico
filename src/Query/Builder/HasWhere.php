<?php

namespace Elastico\Query\Builder;

use Elastico\Models\Model;
use Elastico\Query\Compound\Boolean;
use Elastico\Query\Term\Exists;
use Elastico\Query\Term\Range;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Terms;
use Illuminate\Support\Collection;

trait HasWhere
{
    // public function whereNot(string $field, $value): self
    // // public function whereNot($column, $operator = null, $value = null, $boolean = 'and')
    // {
    //     $value = static::formatFilterValue($value);

    //     return $this->mustNot((new Term())->field($field)->value($value));
    // }

    // public function whereExists(string $field): self
    // {
    //     return $this->filter((new Exists())->field($field));
    // }

    // public function whereNull(string $field): self
    // {
    //     return $this->mustNot((new Exists())->field($field));
    // }

    // public function whereBetween(string $field, array $values): self
    // {
    //     $values = array_values($values);

    //     if (empty($values)) {
    //         return $this;
    //     }

    //     return $this->where($field, '>', $values[0])->where($field, '<', $values[1]);
    // }

    /**
     * Filter by
     * - key operator value
     * - key value (and equal operator)
     * - callable for grouped condition.
     *
     * @param mixed      $column
     * @param null|mixed $operator
     * @param null|mixed $value
     * @param mixed      $boolean
     */
    // public function where(string|\Closure $field, mixed $operator = null, mixed $value = null): self
    public function where($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        if ($field instanceof \Closure) {
            $this->getQuery()->filter($field(new Boolean()));

            return $this;
        }
        if (2 == func_num_args()) {
            $value = $operator;
            $operator = '=';
        }
        $value = static::formatFilterValue($value);

        match ($operator) {
            '>' => $this->filter((new Range())->field($field)->gt($value)),
            '>=' => $this->filter((new Range())->field($field)->gte($value)),
            '<' => $this->filter((new Range())->field($field)->lt($value)),
            '<=' => $this->filter((new Range())->field($field)->lte($value)),
            '=' => match (is_array($value)) {
                true => $this->filter((new Terms())->field($field)->values($value)),
                false => $this->filter((new Term())->field($field)->value($value)),
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
