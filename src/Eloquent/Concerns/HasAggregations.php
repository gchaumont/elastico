<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Query\Response\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Stores aggregation results on the model.
 */
trait HasAggregations
{
    public BaseCollection $aggregations;

    public BaseCollection $responses;

    public array $aggregates;


    public function addAggregations(string $relation, BaseCollection $aggregations)
    {
        $this->aggregations ??= new BaseCollection();
        if (!$this->aggregations->has($relation)) {
            $this->aggregations->put($relation, $aggregations);
        } else {
            $this->aggregations->put($relation, $this->aggregations->get($relation)->merge($aggregations));
        }

        return $this;
    }

    public function getAggregations(null|string $relation = null): BaseCollection
    {
        if ($relation) {
            return $this->aggregations->get($relation);
        }

        return $this->aggregations;
    }

    public function addResponse(string $key, Collection $response): static
    {
        $this->responses ??= new BaseCollection();
        $this->responses->put($key, $response);

        return $this;
    }

    public function getResponse(string $key): Collection
    {
        return $this->responses->get($key) ?? throw new \Exception("No response found for {$key}");
    }
}
