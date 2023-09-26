<?php

namespace Elastico\Query\Builder;

use Elastico\Query\Builder;
use Illuminate\Support\Collection;
use Elastico\Aggregations\Aggregation;
use Illuminate\Database\Eloquent\Relations\Relation;

/** 
 * @mixin Builder
 */
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

    public function mergeAggregations(Builder|Relation $builder): static
    {
        # recursively merge aggregations and sub aggregations

        $merge = function (Collection $base_aggregations, Collection $other_aggregations) use (&$merge): void {
            $other_aggregations->each(function (Aggregation $aggregation, string $name) use ($base_aggregations, &$merge): void {
                if ($base_aggregations->has($name)) {
                    $merge($base_aggregations->get($name)->getAggregations(), $aggregation->getAggregations());
                } else {
                    $base_aggregations->put($name, $aggregation);
                }
            });
        };

        $merge($this->getAggregations(), $builder->getAggregations());

        return $this;
    }
}
