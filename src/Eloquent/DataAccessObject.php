<?php

namespace Elastico\Eloquent;

use ArrayAccess;
use Elastico\Eloquent\Concerns\HasIndexProperties;
use Spatie\LaravelData\Data;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject extends Data implements ArrayAccess
{
    use HasIndexProperties;


    public static function castUsing(array $arguments)
    {
        return new DataCast(static::class, $arguments);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->{$offset});
    }

    public function offsetGet($offset): mixed
    {
        return $this->{$offset};
    }

    public function offsetSet($offset, $value): void
    {
        $this->{$offset} = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->{$offset});
    }
}
