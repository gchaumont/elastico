<?php

namespace Elastico\Query\Builder;

use Elastico\Aggregations\Aggregation;
use Illuminate\Support\Collection;

trait HasAggregations
{
    protected Collection $aggregations;

    public function getAggregations(): Collection
    {
        return $this->aggregations ??= collect();
    }

    public function getAggregation(string $name): null|Aggregation
    {
        return $this->getAggregations()->get($name);
    }

    public function addAggregation(string $name, Aggregation $aggregation): self
    {
        $this->getAggregations()->put($name, $aggregation);

        return $this;
    }

    public function addAggregations(iterable $aggregations): self
    {
        collect($aggregations)
            ->map(fn (Aggregation $aggregation, string $name) => $this->addAggregation($name, $aggregation));

        return $this;
    }

    public function setAggregations(iterable $aggregations): self
    {
        $this->aggregations = collect();

        $this->addAggregations($aggregations);

        return $this;
    }
}
