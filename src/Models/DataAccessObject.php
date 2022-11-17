<?php

namespace Elastico\Models;

use Elastico\Models\Features\Mappable;
use Elastico\Models\Features\Serialisable;
use Elastico\Models\Features\Unserialisable;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject
{
    use Mappable;
    use Serialisable;
    use Unserialisable;

    public function getAttribute(string $attribute): mixed
    {
        if (false !== ($pos = strpos($attribute, '.'))) {
            $attr = substr($attribute, 0, $pos);

            if (!isset($this->{$attr})) {
                return null;
            }

            return $this->{$attr}?->getAttribute(substr($attribute, 1 + $pos));
        }

        if (!isset($this->{$attribute})) {
            return null;
        }

        return $this->{$attribute} ?? null;
        // $path = explode('.', $attribute);

        // $f = array_shift($path);

        // if (empty($this->{$f})) {
        //     return null;
        // }

        // if (count($path)) {
        //     return $this->{$f}->getAttribute(implode('.', $path));
        // }

        // return $this->{$f} ?? null;
    }

    public function setAttribute(string $attribute, mixed $value): static
    {
        if (false !== ($pos = strpos($attribute, '.'))) {
            $this->{substr($attribute, 0, $pos)}->setAttribute(substr($attribute, 1 + $pos), $value);
        } else {
            $this->{$attribute} = $value;
        }

        // return $this->{$attribute} ?? null;
        // $path = explode('.', $attribute);

        // $f = array_shift($path);

        // if (count($path)) {
        //     if (empty($this->{$f})) {
        //         $class = static::getElasticFields()[$f]->propertyType();
        //         $this->{$f} = new $class();
        //     }

        //     $this->{$f}->setAttribute(implode('.', $path), $value);

        //     return $this;
        // }
        // $this->{$f} = $value;

        return $this;
    }

    public function attributeIsSet(string $attribute): bool
    {
        return isset($this->{$attribute});
    }
}
