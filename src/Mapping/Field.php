<?php

namespace Elastico\Mapping;

use Attribute;
use Elastico\Models\DataAccessObject;
use Elastico\Models\Model;

#[\Attribute]
class Field
{
    public readonly \ReflectionProperty $property;

    public bool $index;

    public bool $enabled;

    public string $object;

    public array $fields;

    public string $analyzer;

    public string $element_type;

    public \Closure $propertyCallback;

    public string|array $copy_to;

    public function __construct(
        protected FieldType $type,
        protected string $name,
    ) {
        // code...
    }

    public static function make(string|FieldType $type, string $name): static
    {
        if (!$type instanceof FieldType) {
            $type = FieldType::from($type);
        }

        return new static(type: $type, name: $name);
    }

    public function index(bool $index = true): static
    {
        $this->index = $index;

        return $this;
    }

    public function enabled(bool $enabled = true): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function object(string $class): static
    {
        $this->object = $class;

        return $this;
    }

    public function copyTo(string|array $destination): static
    {
        $this->copy_to = $destination;

        return $this;
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function analyzer(string $analyzer): static
    {
        $this->analyzer = $analyzer;

        return $this;
    }

    public function dims(int $dims): static
    {
        $this->dims = $dims;

        return $this;
    }

    public function similarity(string $similarity): static
    {
        $this->similarity = $similarity;

        return $this;
    }

    public function element_type(string $element_type): static
    {
        $this->element_type = $element_type;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function eachProperty(\Closure $callback): static
    {
        $this->propertyCallback = $callback;

        return $this;
    }

    public function toArray(): array
    {
        $config['type'] = $this->type->name;
        if (isset($this->index)) {
            $config['index'] = $this->index;
        }
        if (isset($this->enabled)) {
            $config['enabled'] = $this->enabled;
        }
        if (isset($this->analyzer)) {
            $config['analyzer'] = $this->analyzer;
        }
        if (isset($this->dims)) {
            $config['dims'] = $this->dims;
        }
        if (isset($this->similarity)) {
            $config['similarity'] = $this->similarity;
        }
        if (isset($this->element_type)) {
            $config['element_type'] = $this->element_type;
        }
        if (isset($this->object)) {
            $config['properties'] = collect($this->object::indexProperties())
                ->keyBy(fn ($prop) => $prop->getName())
                ->map(fn ($prop) => isset($this->propertyCallback) ? call_user_func($this->propertyCallback, $prop) : $prop)
                ->map(fn ($prop) => $prop->toArray())
                ->all()
            ;
        }
        if (isset($this->copy_to)) {
            $config['copy_to'] = $this->copy_to;
        }
        if (isset($this->fields)) {
            $config['fields'] = $this->fields;
        }

        return $config;
    }

    public function withProp(\ReflectionProperty $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function propertyName(): string
    {
        return $this->property->getName();
    }

    public function ownerClass(): string
    {
        return $this->property->class;
    }

    public function propertyType(): string
    {
        if (!empty($this->class)) {
            return $this->class;
        }
        if ($this->property->getType() instanceof \ReflectionUnionType) {
            $types = explode('|', (string) $this->property->getType());

            $types = array_filter($types, fn ($type) => !in_array($type, ['array', 'null', 'string']));

            if (1 == count($types)) {
                return reset($types);
            }
        } elseif ($this->property->getType() instanceof \ReflectionType) {
            return $this->property->getType()->getName();
        }
    }

    public function fieldName(): string
    {
        return $this->name ?? $this->propertyName();
    }

    public function multiObject(): bool
    {
        return match (true) {
            FieldType::nested == $this->type => true,
            empty($this->class) => false,
            default => $this->hasArrayType && class_exists($this->class),
        };
    }

    public function disabled(): bool
    {
        return false === ($this->enabled ?? true);
    }

    public function isPromoted(): bool
    {
        return $this->property->isPromoted();
    }

    public function hasArrayType(): bool
    {
        $types = explode('|', (string) $this->property->getType());

        return in_array('array', $types);
    }

    public function nullable(): bool
    {
        return $this->property->getType()->allowsNull();
    }

    public function shouldSerialise(bool $asRelation, object $object): bool
    {
        $this->property->setAccessible(true);
        if (!$this->property->isInitialized($object)) {
            return false;
        }

        if (is_subclass_of($this->ownerClass(), DataAccessObject::class, true)) {
            return true;
        }
        if ($asRelation && $this->related) {
            return true;
        }
        if ($asRelation && !$this->related) {
            return false;
        }

        return !empty($this->type) || !empty($this->dynamic_template);
    }

    public function shouldIndex(bool $related): bool
    {
        if (false == $related) {
            return true;
        }
        if (is_subclass_of($this->ownerClass(), Model::class, true)) {
            return false;
        }
        if (is_subclass_of($this->ownerClass(), DataAccessObject::class, true)) {
            return true;
        }

        if (!empty($this->dynamic_template)) {
            return false;
        }

        return false;
    }

    public function propCount(): int
    {
        if (isset($this->object)) {
            return collect($this->object::indexProperties())
                ->map(fn ($prop) => $prop->propCount())
                ->sum()
            ;
        }

        return 1;
    }

    public function configuration(): array
    {
        $property = [];
        $property['type'] = $this->type->name;
        if (in_array($this->type, [FieldType::object, FieldType::nested])) {
            if (class_exists($this->propertyType())
            && is_subclass_of($this->propertyType(), Model::class)) {
                if (!empty($this->properties)) {
                    $property['properties'] = array_filter(
                        $this->propertyType()::getIndexProperties(),
                        fn ($prop) => in_array($prop, $this->properties),
                        ARRAY_FILTER_USE_KEY
                    );
                } else {
                    $property['properties'] = $this->propertyType()::getIndexProperties(true);
                }
            } elseif (class_exists($this->propertyType())
            && is_subclass_of($this->propertyType(), DataAccessObject::class)) {
                $property['properties'] = $this->propertyType()::getIndexProperties();
            }
            if (FieldType::nested == $this->type) {
                $property['dynamic'] = 'strict';
            }
        } elseif (FieldType::scaled_float == $this->type) {
            $property = [
                'scaling_factor' => $this->scaling_factor,
            ];
        } else {
            if (isset($this->fields)) {
                $property['fields'] = $this->fields;
            }
        }

        if (false === $this->index) {
            $property['index'] = false;
        }
        if (false === $this->enabled) {
            $property['enabled'] = false;
        }
        if (!empty($this->copy_to)) {
            $property['copy_to'] = $this->copy_to;
        }
        if (!empty($this->analyzer)) {
            $property['analyzer'] = $this->analyzer;
        }

        if (!empty($this->rawProperties)) {
            $property['properties'] = $this->rawProperties;
        }

        return $property;
    }
}
