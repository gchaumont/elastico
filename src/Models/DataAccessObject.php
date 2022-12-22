<?php

namespace Elastico\Models;

use Elastico\Eloquent\Model;
use Elastico\Models\Features\Mappable;
use Elastico\Models\Features\Serialisable;
use Elastico\Models\Features\Unserialisable;
use Illuminate\Contracts\Database\Eloquent\Castable as CastableContract;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject implements CastableContract
{
    use Mappable;
    use HasAttributes;

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

        return new class($class) implements CastsAttributes {
            public function __construct(protected string $class)
            {
            }

            public function get($model, $key, $value, $attributes)
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

            public function set($model, $key, $value, $attributes)
            {
                return [$key => $value->getAttributes()];
            }
        };
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

    // public function getAttribute(string $attribute): mixed
    // {
    //     if (false !== ($pos = strpos($attribute, '.'))) {
    //         $attr = substr($attribute, 0, $pos);

    //         if (!isset($this->{$attr})) {
    //             return null;
    //         }

    //         return $this->{$attr}?->getAttribute(substr($attribute, 1 + $pos));
    //     }

    //     if (!isset($this->{$attribute})) {
    //         return null;
    //     }

    //     return $this->{$attribute} ?? null;
    //     // $path = explode('.', $attribute);

    //     // $f = array_shift($path);

    //     // if (empty($this->{$f})) {
    //     //     return null;
    //     // }

    //     // if (count($path)) {
    //     //     return $this->{$f}->getAttribute(implode('.', $path));
    //     // }

    //     // return $this->{$f} ?? null;
    // }

    // public function setAttribute(string $attribute, mixed $value): static
    // {
    //     if (false !== ($pos = strpos($attribute, '.'))) {
    //         $this->{substr($attribute, 0, $pos)}->setAttribute(substr($attribute, 1 + $pos), $value);
    //     } else {
    //         $this->{$attribute} = $value;
    //     }

    //     // return $this->{$attribute} ?? null;
    //     // $path = explode('.', $attribute);

    //     // $f = array_shift($path);

    //     // if (count($path)) {
    //     //     if (empty($this->{$f})) {
    //     //         $class = static::getElasticFields()[$f]->propertyType();
    //     //         $this->{$f} = new $class();
    //     //     }

    //     //     $this->{$f}->setAttribute(implode('.', $path), $value);

    //     //     return $this;
    //     // }
    //     // $this->{$f} = $value;

    //     return $this;
    // }

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
                : constant($enumClass.'::'.$value);
    }
}
