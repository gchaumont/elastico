<?php

namespace Elastico\Query\Response;

use Closure;
use Elastico\Query\Builder;
use Elastico\Eloquent\Model;
use Elastico\Aggregations\Aggregation;
use Illuminate\Support\LazyCollection;
use Elastico\Models\Builder\EloquentBuilder;
use Elastico\Eloquent\Concerns\ParsesRelationships;
use Illuminate\Support\Collection as BaseCollection;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Elastic Base Response.
 */
class Collection extends EloquentCollection
{
    use ParsesRelationships;

    // protected array $with;

    protected BaseCollection $requested_aggregations;

    public function __construct(
        iterable|LazyCollection $items,
        protected null|int $total = null,
        protected array|BaseCollection|LazyCollection $aggregations = [],
        protected null|array $response = null,
        protected null|Builder $query = null,
        protected null|string $model = null,
    ) {
        $this->items = collect($items)->all();
        // $this->source = $this->items;

        // if ($this->query instanceof ModelBuilder) {
        //     $this->with = $this->query?->getWith() ?? [];
        // }

        // $this->model ??= $this->query?->getModel();
        $this->requested_aggregations = $this->query?->getAggregations() ?? new BaseCollection();
    }

    public function resetItems(array $items)
    {
        $this->items = $items;

        return $this;
    }

    public function total(): int
    {
        return $this->total ?? 0;
    }

    public function aggregations(): LazyCollection|BaseCollection
    {
        try {

            return $this
                ->requested_aggregations
                ->map(fn (Aggregation $agg, string $name): AggregationResponse => $agg->toResponse(response: $this->aggregations[$name]));
        } catch (\Throwable $th) {
            dd($this);
        }

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

    public function getQuery(): null|Builder
    {
        return $this->query;
    }

    public function dd(...$args)
    {
        if (request()->wantsJson()) {
            // response($this->response())->send();
            // response(serialize($this->aggregations()))->send();
        }
        dd($this);
    }

    public function loadAggregation(string|iterable $relations, iterable|Aggregation|Closure $aggregations = []): static
    {
        return $this->loadAggregations([
            [$relations, $aggregations]
        ]);
    }

    public function loadAggregations(iterable $aggregations): static
    {
        if ($this->isNotEmpty()) {
            if (empty($aggregations)) {
                return $this;
            }

            $query = $this->first()->newQueryWithoutRelationships()->withAggregations($aggregations)->take(0);

            $this->items = $query->eagerLoadAggregations($this->items);
        }
        return $this;
    }

    /**
     * Load a set of aggregations over relationship's column onto the collection.
     *
     * @param  array<array-key, (callable(\Illuminate\Database\Eloquent\Builder): mixed)|string>|string  $relations
     * @param  string  $column
     * @param  string|null  $function
     * @return $this
     */
    public function loadAggregate($relations, $column, $function = null)
    {
        if ($this->isEmpty()) {
            return $this;
        }


        if (!is_array($relations)) {
            $relations = [$relations];
        }

        $relations = $this->partitionEloquentElasticRelationships($this->first(), $relations);


        if (count($relations['elastic'])) {

            $query = $this->first()->newQueryWithoutRelationships()->withAggregate($relations['elastic'], $column, $function)->take(0);

            $this->items = $query->eagerLoadAggregations($this->items);

            $this->items = $query->resolveAggregates($this->items);
        }


        if (count($relations['eloquent'])) {
            $collection = parent::loadAggregate($relations['eloquent'], $column, $function);
            $this->items = $collection->all();
        }



        return $this;
    }


    public function loadQueries(callable|iterable $queries): static
    {
        return $this->getBulk($queries)
            ->map(function (BaseCollection $responses, string $model_id): Model {
                $model = $this->get($model_id);

                $responses->each(function (Collection $response, string $query_key) use ($model) {
                    $model->addResponse($query_key, $response);
                });

                return $model;
            })
            ->eloquent();
    }
}
