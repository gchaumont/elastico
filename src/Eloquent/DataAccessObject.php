<?php

namespace Elastico\Eloquent;


use Elastico\Models\Features\Mappable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\Castable as CastableContract;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject implements CastableContract, Arrayable
{
    use HasAttributes;
    use HidesAttributes;

    // protected $dateFormat = \DateTime::ATOM;
    // use Serialisable;
    // use Unserialisable;

    protected $exists = false;

    public function __construct($attributes = [])
    {
        if ($attributes) {
            $this->attributes = $attributes;
        }
        $this->dateFormat = \DateTime::ATOM;
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
}