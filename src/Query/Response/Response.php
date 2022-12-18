<?php

namespace Elastico\Query\Response;

use Elastico\Models\Builder\Builder as ModelBuilder;
use Elastico\Models\Builder\EloquentBuilder;
use Elastico\Query\Builder;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;

/**
 * Elastic Base Response.
 */
class Response extends EloquentCollection
{
    protected array $with;

    protected BaseCollection $query_aggs;

    public function __construct(
        iterable|LazyCollection $items,
        protected null|int $total = null,
        protected iterable|BaseCollection|LazyCollection $aggregations = [],
        protected null|array $response = null,
        protected null|Builder|EloquentBuilder $query = null,
        protected null|string $model = null,
    ) {
        $this->items = $items;

        // $this->source = $this->items;

        if ($this->query instanceof ModelBuilder) {
            $this->with = $this->query?->getWith() ?? [];
        }

        // $this->model ??= $this->query?->getModel();
        $this->query_aggs = $this->query?->getAggregations() ?? new BaseCollection();
    }

    public function hits(): Collection
    {
        return new Collection($this->items);
    }

    public function resetItems(array $items)
    {
        $this->items = $items;

        return $this;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function aggregations(): LazyCollection|BaseCollection
    {
        return $this->query_aggs->map(fn ($agg): AggregationResponse => $agg->toResponse(response: $this->aggregations[$agg->getName()]));

        return collect($this->aggregations);
    }

    public function aggregation(string $name): null|AggregationResponse
    {
        return $this->aggregations()->get($name);
    }

    public function response(): array
    {
        return $this->response;
    }

    public function dd(...$args)
    {
        if (request()->wantsJson()) {
            // response($this->response())->send();
            // response(serialize($this->aggregations()))->send();
        }
        dd($this);
    }
}
