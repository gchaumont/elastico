<?php

namespace Elastico\Models\Relations;

use Elastico\Models\Builder\Builder;
use Elastico\Models\Model;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Defines a relationship between multiple models.
 */
abstract class Relation extends Builder
{
    protected string $name;

    protected null|string $local;

    protected null|string $related;

    protected null|string $localKey;

    protected null|string $foreignKey;

    abstract public function match(iterable $models, iterable $results, string $relation): iterable;

    abstract public function getResults(): iterable|object;

    abstract public function addConstraints(): static;

    abstract public function addEagerConstraints(iterable $models): static;

    public function getName(): string
    {
        return $this->name;
    }

    public function forProperty(ReflectionProperty $property): static
    {
        $this->name = $property->getName();

        $this->local ??= $property->getDeclaringClass()->getName();

        if (empty($this->related)
            && !($property->getType() instanceof ReflectionUnionType)
            && class_exists($property->getType())
            && is_subclass_of($property->getType()->getName(), Model::class, true)
        ) {
            $this->related = $property->getType();
        }

        return $this;
    }

    public function setupProperty(ReflectionProperty $property): static
    {
        return $this;
    }

    /**
     * Get all of the primary keys for an array of models.
     */
    public function getKeys(iterable $models, string $key = null): array
    {
        return collect($models)
            ->map(fn (Model $value) => $key ? $value->getAttribute($key) : $value->getKey())
            ->values()
            ->unique(null, true)
            ->sort()
            ->all()
        ;
    }

    public function getParentKey()
    {
        return $this->getRelated()->getAttribute($this->getLocalKey());
    }

    public function getRelated(): Model
    {
        $class = $this->related;

        return new $class();
    }

    public function getLocal(): Model
    {
        $class = $this->local;

        return new $class();
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey ?? $this->getRelated()->getForeignKey();
    }

    public function getLocalKey(): string
    {
        return $this->localKey ?? $this->getRelated()->getKeyName();
    }
}
