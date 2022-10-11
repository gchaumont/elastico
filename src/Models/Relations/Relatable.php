<?php

namespace Elastico\Models\Relations;

use Elastico\Query\Response\Collection;
use Exception;
use Illuminate\Support\Collection as BaseCollection;
use ReflectionAttribute;

trait Relatable
{
    public function queryRelated(string $relation): Relation
    {
        return $this->getRelation($relation)->addEagerConstraints([$this]);
    }

    public function getRelated(string $relation): mixed
    {
        return $this->getAttribute(
            attribute: $this->getRelation($relation)->getAttributeName()
        );
    }

    public function setRelated(string $relation, mixed $value): static
    {
        return $this->setAttribute(
            attribute: $this->getRelations()->get($relation)->getName(),
            value: $value
        );

        return $this;
    }

    public function isRelation(string $relation): bool
    {
        return $this->getRelations()->has($relation);
    }

    public function getRelation(string $relation): Relation
    {
        return $this->getRelations()->get($relation) ?? throw new Exception('Relation ['.$relation.'] not found on Model '.static::class);
    }

    public function getForeignKey(): string
    {
        return strtolower(class_basename($this)).'.'.$this->getKeyName();
    }

    public function getRelations(): BaseCollection
    {
        static $relations_cache;

        if (!isset($relations_cache[static::class])) {
            $relations = collect();
            foreach (static::get_reflection_properties() as $property) {
                foreach ($property->getAttributes(Relation::class, ReflectionAttribute::IS_INSTANCEOF) as $relation) {
                    $relations->put($property->getName(), $relation->newInstance()->forProperty($property));
                }
            }
            $relations_cache[static::class] = $relations;
        }

        return $relations_cache[static::class];
    }

    public function load($relations): self
    {
        return Collection::make([$this])
            ->load($relations)
            ->first()
        ;
    }
}
