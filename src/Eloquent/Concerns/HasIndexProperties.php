<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Mapping\Field;
use Illuminate\Support\Collection;
use ReflectionAttribute;

trait HasIndexProperties
{
    public static function indexProperties(): array
    {
        return [];
    }

    public static function getIndexProperties(): array
    {
        // static $cache;

        // if (isset($cache[static::class])) {
        //     // return $cache[static::class];
        // }

        $properties = collect(static::indexProperties());

        $reflectionClass = new \ReflectionClass(static::class);

        collect($reflectionClass->getAttributes(Field::class))
            ->map(static fn(ReflectionAttribute $attribute): Field => $attribute->newInstance())
            ->tap(static fn(Collection $props) => $properties->push(...$props));


        collect($reflectionClass->getProperties())
            ->flatMap(static fn(\ReflectionProperty $property) => collect($property->getAttributes(Field::class))
                ->map(static fn(ReflectionAttribute $attribute): Field => $attribute->newInstance())
                ->each(static fn(Field $field) => $field->name($property->getName())))
            ->tap(static fn(Collection $props) => $properties->push(...$props));

        collect(class_uses_recursive(static::class))
            ->each(static function ($trait) use ($properties): void {
                collect((new \ReflectionClass($trait))->getAttributes(Field::class))
                    ->map(fn(ReflectionAttribute $attribute): Field => $attribute->newInstance())
                    ->tap(static fn(Collection $props) => $properties->push(...$props));
            })
            ->each(static function ($trait) use ($properties): void {
                collect((new \ReflectionClass($trait))->getProperties())
                    ->flatMap(static fn(\ReflectionProperty $property) => collect($property->getAttributes(Field::class))
                        ->map(static fn(ReflectionAttribute $attribute): Field => $attribute->newInstance())
                        ->each(static fn(Field $field) => $field->name($property->getName())))
                    ->tap(static fn(Collection $props) => $properties->push(...$props));
            });


        return $cache[static::class] = $properties->all();
    }
}
