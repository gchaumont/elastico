<?php

namespace Elastico\Models\Features;

use Elastico\Models\Model;
use Elastico\Query\Response\Collection;
use ReflectionNamedType;

trait Relatable
{
    public static function getForeignKey(): string
    {
        return 'id';
    }

    public function load($relations): self
    {
        return Collection::make([$this])
            ->load($relations)
            ->first()
        ;
    }

    public static function getClassForRelation(string $relation): string
    {
        return collect(static::getElasticFields())
            ->first(fn ($rel) => $rel->name = $relation)
            // ->filter(fn ($type) => is_subclass_of($type, Model::class))
            ->propertyType()
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
