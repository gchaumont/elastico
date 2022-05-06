<?php

namespace Gchaumont\Query\Builder;

use Gchaumont\Aggregations\Aggregation;
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

    public function addAggregation(Aggregation $aggregation): self
    {
        $this->getAggregations()->put($aggregation->getName(), $aggregation);

        return $this;
    }

    public function addAggregations(iterable $aggregations): self
    {
        collect($aggregations)->map(fn ($agg) => $this->addAggregation($agg));

        return $this;
    }
}
