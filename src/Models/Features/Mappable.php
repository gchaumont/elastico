<?php

namespace Elastico\Models\Features;

use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;
use Elastico\Models\DataAccessObject;
use Elastico\Models\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionUnionType;

trait Mappable
{
    public static function getFieldType(string $property): FieldType
    {
        return static::getField($property)->type;
    }

    public static function getField(string $field): Field
    {
        $paths = explode('.', $field);

        $field = static::getElasticFields()[array_shift($paths)];

        if (count($paths)) {
            return $field->propertyType()::getField(implode('.', $paths));
        }

        return $field;
    }

    public static function getPropertyType(string $property): string
    {
        $paths = explode('.', $property);

        $field = static::getElasticFields(array_shift($paths));

        if (count($paths)) {
            return $field->propertyType()::getPropertyType(implode('.', $paths));
        }

        return $field->getPropertyType();
    }

    public static function getElasticFields(): array
    {
        static $fields;

        if (isset($fields[static::class])) {
            return $fields[static::class];
        }

        $fields[static::class] = [];

        foreach (static::get_reflection_properties() as $property) {
            foreach ($property->getAttributes(Field::class) as $attribute) {
                $attributeInstance = $attribute->newInstance()->withProp($property);

                if ($property->getType() instanceof ReflectionUnionType) {
                    $types = explode('|', (string) $property->getType());

                    // $types = array_filter($types, fn ($type) => 'array' !== $type && 'null' !== $type);
                    $types = array_filter($types, fn ($type) => 'null' !== $type);
                    if (1 == count($types)) {
                        $attribute->class ??= reset($types);
                    }
                } else {
                    $attribute->class ??= $property->getType()->getName();
                }

                $fields[static::class][$attributeInstance->fieldName()] = $attributeInstance;
            }
        }

        return $fields[static::class];
    }

    public static function get_reflection_properties(): array
    {
        static $reflectionProperties;
        //$constructorParameters = $reflectionClass->getConstructor()?->getParameters() ?? [];

        return $reflectionProperties[static::class] ??= (new ReflectionClass(static::class))->getProperties();
    }

    public static function getIndexProperties($related = false): array
    {
        $properties = [];

        $fields = static::getElasticFields();

        $fields = array_filter($fields, fn ($field) => $field->shouldIndex(related: $related));

        foreach ($fields as $field) {
            $properties[$field->fieldName()] = $field->configuration();
        }
        if (true == $related && is_subclass_of(static::class, Model::class)) {
            $properties['id'] = ['type' => 'keyword'];
        }

        return $properties;
    }

    public static function getAllFields(bool $related = false): Collection
    {
        $fields = [];
        foreach (static::get_reflection_properties() as $property) {
            foreach ($property->getAttributes(Field::class) as $attribute) {
                $nested = false;
                $attributeInstance = $attribute->newInstance()->withProp($property);

                if (!$attributeInstance->shouldIndex($related)) {
                    continue;
                }

                if ($property->getType() instanceof ReflectionUnionType) {
                    $types = explode('|', (string) $property->getType());

                    $types = array_filter($types, fn ($type) => !in_array($type, ['array', 'null']));
                } else {
                    $types = [$property->getType()->getName()];
                }
                foreach ($types as $type) {
                    if (class_exists($type) && is_subclass_of($type, DataAccessObject::class, true)) {
                        foreach ($type::getAllFields(related: true) as $key => $field) {
                            $nested = true;
                            $fields[$attributeInstance->fieldName().'.'.$key] = $field;
                        }
                    }
                }
                if (false == $nested) {
                    $fields[$attributeInstance->fieldName()] = $attributeInstance;
                }
            }
        }
        if ($related && is_subclass_of(static::class, Model::class, true)) {
            $fields['id'] = '';
        }

        return collect($fields);
    }
}
