<?php

namespace Elastico\Mapping;

use App\Support\Data\TransferObjects\DataTransferObject;
use Attribute;
use Elastico\Models\Model;
use ReflectionProperty;

#[Attribute]
class Field
{
    public readonly ReflectionProperty $property;

    public function __construct(
        public null|FieldType $type = null,
        public null|string $name = null,
        public null|string $locale = null,
        public null|bool $doc_values = null,
        public null|bool $eager_global_ordinals = null,
        public null|array $fields = null,
        public null|int $ignore_above = null,
        public null|bool $index = null,
        public null|string|array $copy_to = null,
        public null|string $index_options = null,
        public null|array $meta = null,
        public null|bool $norms = null,
        public null|string $null_value = null,
        public null|string $on_script_error = null,
        public null|string $script = null, // uses script instead of _source to calc value
        public null|bool $store = null,
        public null|string $similarity = null,
        public null|string $normalizer = null,
        public null|bool $split_queries_on_whitespace = null,
        public null|int $scaling_factor = null,
        public null|float $boost = null,
        public null|int $depth_limit = null,
        public null|array $relations = null,
        public null|bool|string $dynamic = null,
        public null|array $properties = null,
        public null|bool $include_in_parent = null,
        public null|bool $include_in_root = null,
        public null|bool $enabled = null,
        public int|null $dims = null,
        public bool|null $positive_score_impact = null,
        public null|bool $ignore_malformed = null,
        public null|bool $coerce = null,
        public null|int $max_shingle_size = null,
        public null|string $analyzer = null,
        public null|string $search_analyzer = null,
        public null|string $search_quote_analyzer = null,
        public null|string $term_vector = null,
        public null|bool $preserve_separators = null,
        public null|bool $preserve_position_increments = null,
        public null|int $max_input_length = null,
        public null|bool $fielddata = null,
        public null|array $index_prefixes = null,
        public null|bool $index_phrases = null,
        public null|int $position_increment_gap = null,
        public null|string $enable_position_increments = null,
        public null|array $rawProperties = null,
    ) {
        // code...
    }

    public function withProp(ReflectionProperty $property): static
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
        if ($this->property->getType() instanceof \ReflectionUnionType) {
            $types = explode('|', (string) $this->property->getType());

            $types = array_filter($types, fn ($type) => 'array' !== $type && 'null' !== $type);

            if (1 == count($types)) {
                return reset($types);
            }
        }
        if ($this->property->getType() instanceof \ReflectionType) {
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

        if (is_a($this->ownerClass(), DataTransferObject::class, true)) {
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
        if (is_subclass_of($this->ownerClass(), DataTransferObject::class, true)) {
            return true;
        }

        if (!empty($this->dynamic_template)) {
            return false;
        }

        return false;
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
            && is_subclass_of($this->propertyType(), DataTransferObject::class)) {
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
