<?php

namespace Gchaumont\Models\Features;

use App\Support\Traits\Reflective;
use Gchaumont\Mapping\Field;
use Gchaumont\Models\DataAccessObject;
use Gchaumont\Models\Model;
use Illuminate\Support\Collection;
use ReflectionUnionType;

trait Mappable
{
    use Reflective;

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

                    $types = array_filter($types, fn ($type) => 'array' !== $type && 'null' !== $type);
                    if (1 == count($types)) {
                        $attribute->class = reset($types);

                        if (class_exists($attribute->class) && in_array('App\\Elastic\\Indexable', class_uses($attribute->class))) {
                            $attribute->isRelationClass = true;
                        }
                    }
                } else {
                    $attribute->class = $property->getType()->getName();
                    if (class_exists($attribute->class) && in_array('App\\Elastic\\Indexable', class_uses($attribute->class))) {
                        $attribute->isRelationClass = true;
                    }
                }

                $fields[static::class][$attributeInstance->fieldName()] = $attributeInstance;
            }
        }

        return $fields[static::class];
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

    public static function getTotalFieldCount(): int
    {
        // return count(static::getIndexProperties());
        $props = static::getIndexProperties();

        $total = 0;
        do {
            $total += count($props);
            $props = array_values(array_filter($props, fn ($prop) => isset($prop['properties'])));
            $total -= count($props);
            $props = array_merge(...(array_map(fn ($prop) => array_values($prop['properties']), $props)));
        } while ($props);

        return $total;
    }
}
