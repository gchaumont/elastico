<?php

namespace Elastico\Models\Features;

// use App\Models\Crawler\Domain;
use Carbon\Carbon;
use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;
use Elastico\Models\DataAccessObject;
use Elastico\Models\Model;
use GuzzleHttp\Ring\Future\FutureArray;
use stdClass;

trait Unserialisable
{
    public static function unserialise(array|FutureArray $document): static
    {
        return (new static(...static::prepareConstructorProperties($document['_source'])))
            ->initialiseIdentifiers(id: $document['_id'] ?? $document['id'], index: $document['_index'] ?? null)
            ->addSerialisedData($document['_source'])
        ;
    }

    public static function unserialiseRelated($data): static
    {
        // HOTFIX
        // if (Domain::class == static::class) {
        //     $data['id'] ??= $data['domain'];
        // }

        return (new static(...static::prepareConstructorProperties($data)))
            ->initialiseIdentifiers(id: $data['id'])
            ->addSerialisedData($data)
        ;
    }

    public function addSerialisedData(array $source): static
    {
        foreach (static::getElasticFields() as $field) {
            if (!array_key_exists($field->fieldName(), $source)) {
                continue;
            }
            if (!isset($this->{$field->propertyName()})) {
                $this->{$field->propertyName()} = static::unserialiseValue($field, $source[$field->fieldName()]);

                continue;
            }

            if ($this->{$field->propertyName()} instanceof Model) {
                $this->{$field->propertyName()}->addSerialisedData($source[$field->fieldName()]);

                continue;
            }
            if ($this->{$field->propertyName()} instanceof DataAccessObject) {
                $this->{$field->propertyName()}->addSerialisedData($source[$field->fieldName()]);

                continue;
            }
        }

        return $this;
    }

    public static function prepareConstructorProperties(array $source): array
    {
        $constructorProperties = [];
        foreach (static::getElasticFields() as $field) {
            if ($field->isPromoted() && array_key_exists($field->fieldName(), $source)) {
                // if (is_null($value) && !$field->nullable()) {
                //     continue;
                // }
                $constructorProperties[$field->propertyName()] = static::unserialiseValue($field, $source[$field->fieldName()]);
            }
        }

        return $constructorProperties;
    }

    public static function unserialiseValue(Field $field, mixed $value): mixed
    {
        return match (true) {
            'array' == $field->propertyType() && is_null($value) => [], // TODO: remove hotfix
            is_null($value) => $value,
            $field->multiObject() => array_map(fn ($val) => static::unserialiseValueItem($field, $val), $value),
            default => static::unserialiseValueItem($field, $value)
        };
    }

    public static function unserialiseValueItem(Field $field, mixed $value): mixed
    {
        $class = $field->propertyType();

        try {
            return match (true) {
                FieldType::date == $field->type => match (true) {
                    is_a($class, \DateTime::class, true) => Carbon::parse($value),
                    is_a($class, \DateTimeImmutable::class, true) => Carbon::parse($value)->toImmutable(),
                },

            is_a($class, Model::class, true) => $class::unserialiseRelated($value),
            is_subclass_of($class, DataAccessObject::class, true) => (new $class())->addSerialisedData($data),

            enum_exists($class) => $class::tryFrom($value) ?? $class::unserialise($value),

            is_a($class, stdClass::class, true) => (object) $value,

            is_scalar($value),
            is_array($value) => $value,
            };
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error unserialising field {$field->fieldName()} value: ''".json_encode($value)."'' ".$e->getMessage());
        }
    }
}
