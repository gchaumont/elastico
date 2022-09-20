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

    public function getAttribute(string $field): mixed
    {
        return $this->getFieldValue(field: $field);
    }

    public function setAttribute(string $field, mixed $value): static
    {
        return $this->setFieldValue(field: $field, value: $value);
    }

    public function getFieldValue(string $field): mixed
    {
        $path = explode('.', $field);

        $f = array_shift($path);

        if (empty($this->{$f})) {
            return null;
        }

        if (count($path)) {
            return $this->{$f}->getFieldValue(implode('.', $path));
        }

        return $this->{$f} ?? null;
    }

    public function setFieldValue(string $field, mixed $value): static
    {
        if ('_id' == $field) {
            return $this;
        }
        $path = explode('.', $field);

        $f = array_shift($path);

        if (count($path)) {
            if (empty($this->{$f})) {
                $class = static::getElasticFields()[$f]->propertyType();
                $this->{$f} = new $class();
            }

            $this->{$f}->setFieldValue(implode('.', $path), $value);

            return $this;
        }
        $this->{$f} = $value;

        return $this;
    }

    // public function getField(string $field): mixed
    // {
    //     $path = explode('.', $field);

    //     $f = array_shift($path);

    //     if (empty($this->{$f})) {
    //         return null;
    //     }

    //     if (count($path)) {
    //         return $this->{$f}->getField(implode('.', $path));
    //     }

    //     return $this->{$f} ?? null;
    // }

    // public function setField(string $field, mixed $value): static
    // {
    //     if ('_id' == $field) {
    //         return $this;
    //     }
    //     $path = explode('.', $field);

    //     $f = array_shift($path);

    //     if (count($path)) {
    //         if (empty($this->{$f})) {
    //             $class = static::getElasticFields()[$f]->propertyType();
    //             $this->{$f} = new $class();
    //         }

    //         $this->{$f}->setField(implode('.', $path), $value);

    //         return $this;
    //     }
    //     $this->{$f} = $value;

    //     return $this;
    // }
}
