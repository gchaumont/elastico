<?php

namespace Elastico\Models\Features;

use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;
use Elastico\Models\DataAccessObject;
use Elastico\Models\Model;
use Exception;
use stdClass;
use Throwable;

trait Serialisable
{
    public function serialiseRelated(): array
    {
        return ['id' => $this->get_id()];
    }

    public function serialise(bool $asRelation = false): array
    {
        $serialised = [];

        foreach (static::getElasticFields() as $field) {
            if ($field->shouldSerialise(false, $this)) {
                $data = $this->serialiseField($field, true);

                if (!empty($field->properties)) {
                    $data = array_filter(
                        $data,
                        fn ($key) => in_array($key, $field->properties),
                        ARRAY_FILTER_USE_KEY
                    );
                }

                $serialised[$field->fieldName()] = $data;
            }
        }

        return $serialised;
    }

    public function serialiseField(Field $field, bool $asRelation): mixed
    {
        $value = $this->{$field->propertyName()};

        try {
            return match (true) {
                is_null($value) => $value,
                $field->disabled() => $value,
                $value instanceof stdClass ,
                is_array($value) => array_map(fn ($val) => static::serialiseValue($field, $val), (array) $value),
                default => static::serialiseValue($field, $value)
            };
        } catch (Throwable $e) {
            throw new Exception('Field Serialisation Error: '.$e->getMessage()."\n".implode(', ', [
                'Property: '.$field->fieldName(),
                'Type: '.$field->type->name,
                'Value: '.json_encode($value),
            ]));
        }
    }

    public static function serialiseValue(Field $field, mixed $value): mixed
    {
        return match ($field->type) {
            FieldType::object,
            FieldType::nested => match (true) {
                is_a($value, stdClass::class) => (array) $value,
                is_subclass_of($value, Model::class) => $value->serialiseRelated(),
                is_subclass_of($value, DataAccessObject::class) => $value->serialise(),
                is_subclass_of($value, Serialisable::class) => $value->serialise(asRelation: true),
                default => throw new Exception("Field not castable.\n".implode(', ', [
                    'name: '.$field->fieldName(),
                    'type: '.$field->type->name,
                    'proptype: '.$field->propertyType(),
                    'property: '.$field->propertyName(),
                    'owner: '.$field->ownerClass(),
                ])),
            },

            FieldType::keyword,
            FieldType::text,
            FieldType::completion,
            FieldType::search_as_you_type,
            FieldType::token_count => (string) match (true) {
                // 'object' => $value->value, // enums
                is_object($value) && enum_exists(get_class($value)) => $value->value,
                $value instanceof Model => $value->get_id(),
                is_string($value) => $value,
                is_array($value) => $value,
                default => throw new Exception('Field not castable to string'),
            },

          FieldType::integer => (int) match (true) {
              // 'object' => $value->value, // enums
                is_object($value) && enum_exists(get_class($value)) => $value->value,
                $value instanceof Model => $value->get_id(),
                is_string($value) => $value,
                is_array($value) => $value,
                default => $value,
          },

            FieldType::rank_features,
            FieldType::rank_feature,
            FieldType::long,
            FieldType::short,
            FieldType::byte => (int) $value,

            FieldType::float,
            FieldType::double,
            FieldType::half_float,
            FieldType::scaled_float,
            FieldType::unsigned_long => (float) $value,

            FieldType::integer_range,
            FieldType::float_range,
            FieldType::long_range,
            FieldType::double_range,
            FieldType::ip_range,
            FieldType::geo_point,
            FieldType::geo_shape,
            FieldType::point,
            FieldType::shape,
            FieldType::date_range => (array) $value->serialise(),

            FieldType::boolean => (bool) $value,

            FieldType::date => $value->format('c'),

            FieldType::ip => (string) $value,

             default => $value, // for GEOPOINT where field needs to be indexed without type
             //default => throw new Exception("Field type {$field->name} not castable"),
        };
    }
}
