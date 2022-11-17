<?php

namespace Elastico\Models\Relations;

use Attribute;
use Elastico\Models\Model;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo extends Relation
{
    public function __construct(
        // Related (owner) Model (inferred from attribute type / method return type)
        protected null|string $related = null,
        // Parent Model
        protected null|string $local = null, // inferred by calling class
        protected null|string $foreignKey = null, // inferred by parent_name .  parent_id_name
        protected null|string $ownerKey = null, // inferred by parent_id_name
    ) {
        parent::__construct(null, model: $related);
    }

    public function addConstraints(): static
    {
    }

    public function getResults(): iterable|object
    {
        if (!empty($this->foreignKey)) {
            return $this->where($this->getForeignKey(), $this->ids)
                ->take(10000)
                ->get()
                ->hits()
            ;
        }

        return $this->findMany($this->ids);
    }

    public function addEagerConstraints(iterable $models): static
    {
        $this->ids = collect($models)
            // ->pipe(fn ($s) => response($s)->send())
            ->map(fn (Model $m) => $m->getAttribute($this->getLocalKey()))
            ->filter()
            ->unique()
            ->values()
        ;

        return $this;
        // return $this->where(
        //     $this->getForeignKey(),
        //     collect($models)->map(fn (Model $m) => $m->getAttribute($this->getLocalKey()))
        // )->dd();
    }

    public function match(iterable $models, iterable $results, string $relation): iterable
    {
        $foreignKey = $this->getForeignKey();

        $results = collect($results)
            ->keyBy(fn (object $result) => $result->getAttribute($foreignKey))
        ;

        $localKey = $this->getLocalKey();

        return collect($models)->each(function (Model $model) use ($relation, $results, $localKey) {
            $model->setRelated(
                relation: $relation,
                value: $results->get($model->getAttribute($localKey))
            )

            ;
            // if ($related = $results->get($model->getAttribute($localKey))) {
            //     $model->setRelated($relation, $related);
            // } elseif (!$model->isRelationLoaded(relation: $related)) {
            //     $model->setRelated($relation, null);
            // }
        });
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey ?? $this->getRelated()->getKeyName();
    }

    public function getLocalKey(): string
    {
        return $this->ownerKey ?? $this->name.'.'.$this->getRelated()->getKeyName();
    }
}
