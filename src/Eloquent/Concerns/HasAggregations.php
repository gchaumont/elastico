<?php

namespace Elastico\Eloquent\Concerns;


use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Elastico\Relations\ElasticRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;

/**
 * Stores aggregation results on the model.
 */
trait HasAggregations
{
    public Collection $aggregations;

    public array $aggregates;


    public function addAggregations(string $relation, Collection $aggregations)
    {
        $this->aggregations ??= new Collection();
        if (!$this->aggregations->has($relation)) {
            $this->aggregations->put($relation, $aggregations);
        } else {
            $this->aggregations->put($relation, $this->aggregations->get($relation)->merge($aggregations));
        }

        return $this;
    }

    public function getAggregations(string $relation = null): Collection
    {
        if ($relation) {
            return $this->aggregations->get($relation);
        }

        return $this->aggregations;
    }
}
