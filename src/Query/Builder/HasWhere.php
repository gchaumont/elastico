<?php

namespace Gchaumont\Query\Builder;

use Gchaumont\Models\Model;
use Gchaumont\Query\Term\Exists;
use Gchaumont\Query\Term\Range;
use Gchaumont\Query\Term\Term;
use Gchaumont\Query\Term\Terms;
use Illuminate\Support\Collection;

trait HasWhere
{
    public function whereNot(string $field, $value): self
    {
        $value = static::formatFilterValue($value);

        return $this->mustNot((new Term())->field($field)->value($value));
    }

    public function whereExists(string $field): self
    {
        return $this->filter((new Exists())->field($field));
    }

    public function whereNull(string $field): self
    {
        return $this->mustNot((new Exists())->field($field));
    }

    public function where(string $field, $operator = null, $value = null): self
    {
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
        };

        return $this;
    }

    public static function formatFilterValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        return match (is_object($value)) {
            false => $value,
            true => match (true) {
                $value instanceof Model => $value->get_id(),
                $value instanceof \DateTime => $value->format(\DateTime::ATOM),
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \UnitEnum => $value->name,
                $value instanceof \Stringable => (string) $value,
                default => $value,
            }
        };
    }
}
