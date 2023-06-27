<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Query\Response\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Elastico\Eloquent\Relations\ElasticRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;

/**
 * Stores aggregation results on the model.
 */
trait HasAggregations
{
    public Collection $aggregations;

    public Collection $responses;

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

    public function addResponse(string $key, Response $response): static
    {
        $this->responses ??= new Collection();
        $this->responses->put($key, $response);

        return $this;
    }

    public function getResponse(string $key): Response
    {
        return $this->responses->get($key);
    }
}
