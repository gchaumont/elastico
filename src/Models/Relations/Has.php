<?php

namespace Elastico\Models\Relations;

use Attribute;
use Elastico\Models\Model;

/**
 * One model can have one or many related
 * models containing an identifier
 * present on this model.
 */
abstract class Has extends Relation
{
    public function __construct(
        // Related Model (inferred from attribute type / method return type)
        protected null|string $related = null,
        // Parent Model
        protected null|string $parent = null, // inferred by calling class
        protected null|string $foreignKey = null, // inferred by parent_name .  parent_id_name
        protected null|string $localKey = null, // inferred by parent_id_name
    ) {
        parent::__construct(null, model: $related);
    }

    public function addConstraints(): static
    {
        $query = $this->getRelationQuery();

        $query->where($this->getForeignKey(), $this->parentKey());

        return $this;
    }

    public function match(iterable $models, iterable $results, string $relation): iterable
    {
        $foreignKey = $this->getForeignKey();

        $localKey = $this->getLocalKey();

        $results = collect($results);

        if (is_array(collect($results)->first()?->getAttribute($foreignKey))) {
            return collect($models)->each(function (Model $model) use ($relation, $results, $foreignKey, $localKey) {
                $related = $results->filter(fn ($r) => collect($r->getAttribute($foreignKey))->contains($model->getAttribute($localKey)));

                $model->setRelated($relation, $related);
            });
        }

        $results = collect($results)
            ->keyBy(fn (object $result) => $result->getAttribute($foreignKey))
        ;

        return collect($models)->each(function (Model $model) use ($relation, $results, $foreignKey, $localKey) {
            $related = $results->filter(fn ($r) => $r->getAttribute($foreignKey) == $model->getAttribute($localKey));

            $model->setRelated($relation, $related);
        });
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey ?? $this->getLocal()->getForeignKey();
    }

    public function addEagerConstraints(iterable $models): static
    {
        return $this->where(
            $this->getForeignKey(),
            collect($models)
                ->map(fn (Model $model) => $model->getAttribute($this->getLocalKey()))
                ->filter()
                ->unique()
                ->all()
        );
    }
}
