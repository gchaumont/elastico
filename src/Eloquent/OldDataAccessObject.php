<?php

namespace Elastico\Eloquent;

use ArrayAccess;
use Elastico\Models\Features\Mappable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Contracts\Database\Eloquent\Castable as CastableContract;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject implements CastableContract, Arrayable, ArrayAccess
{
    use HasAttributes;
    use HidesAttributes;

    // protected $dateFormat = \DateTime::ATOM;
    // use Serialisable;
    // use Unserialisable;

    protected $exists = false;

    protected static $objectsShouldPreventAccessingMissingAttributes = true;

    public function __construct($attributes = [])
    {
        $this->dateFormat = \DateTime::ATOM;
        if ($attributes) {
            // $this->attributes = $attributes;
            foreach ($attributes as $key => $value) {
                $this->setAttribute($key, $value);
            }
        }
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }


    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        try {
            return !is_null($this->getAttribute($offset));
        } catch (MissingAttributeException) {
            return false;
        }
    }


    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @return string
     */
    public static function castUsing(array $arguments)
    {
        $class = static::class;

        return new class($class) implements CastsAttributes
        {
            public function __construct(protected string $class)
            {
            }

            public function get($model, string $key, mixed $value, array $attributes)
            {
                $class = $this->class;

                if (is_subclass_of($class, Model::class)) {
                    return (new $class())->forceFill($value);
                }

                if (is_subclass_of($class, DataAccessObject::class) && !empty($value)) {
                    if ($value instanceof Arrayable) {
                        $value = $value->toArray();
                    }
                    return (new $class())->setRawAttributes($value);
                }

                return new $class($value);
            }

            public function set($model, string  $key, mixed $value, array $attributes)
            {
                if ($value == null) {
                    return [$key => null];
                }

                return [$key => $value->getAttributes()];
            }
        };
    }

    public function toArray()
    {
        return array_merge($this->attributesToArray());
    }

    public function getIncrementing()
    {
        return false;
    }

    public function relationResolver()
    {
        return null;
    }

    public function relationLoaded()
    {
        return false;
    }

    public function usesTimestamps()
    {
        return false;
    }

    public function attributeIsSet(string $attribute): bool
    {
        return isset($this->{$attribute});
    }

    /**
     * Get an enum case instance from a given class and value.
     *
     * @param string     $enumClass
     * @param int|string $value
     *
     * @return \BackedEnum|\UnitEnum
     */
    protected function getEnumCaseFromValue($enumClass, $value)
    {
        return is_subclass_of($enumClass, \BackedEnum::class)
            ? $enumClass::tryFrom($value)
            : constant($enumClass . '::' . $value);
    }

    public static function preventsAccessingMissingAttributes()
    {
        return static::$objectsShouldPreventAccessingMissingAttributes;
    }
}
