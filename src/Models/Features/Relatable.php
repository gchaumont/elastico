<?php

namespace Gchaumont\Models\Features;

use Gchaumont\Models\Model;
use Gchaumont\Query\Response\Collection;
use ReflectionNamedType;

trait Relatable
{
    public function load($relations): self
    {
        return Collection::make([$this])
            ->load($relations)
            ->first()
        ;
    }

    public static function getPropertyNameForClass(string $class): ?string
    {
        $props = static::get_reflection_properties();
        foreach ($props as $prop) {
            $type = $prop->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() == $class) {
                return $prop->getName();
            }
        }

        return null;
    }

    public static function getAllRelations(): array
    {
        return collect(static::getElasticFields())
            // ->mapWithKeys(fn ($field) => [$field->fieldName() => $field->propertyType()])
            ->map(fn ($field) => $field->propertyType())
            ->filter(fn ($type) => is_subclass_of($type, Model::class))
            ->all()
        ;
    }
}
