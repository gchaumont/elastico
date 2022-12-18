<?php

namespace Elastico\Query\Response;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Models\Builder\Builder as ModelBuilder;
use Elastico\Models\Builder\EloquentBuilder;
use Elastico\Models\DataAccessObject;
use Elastico\Models\Model;
use Elastico\Query\Builder;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Http\Promise\Promise;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;

/**
 * Elastic Base Response.
 */
class PromiseResponse extends Response
{
    protected $get_total;

    protected $get_hits;

    protected $get_aggregations;

    protected ?int $total;

    protected array $with;

    protected $onFulfilled;

    protected $onRejected;

    protected $items;

    protected BaseCollection $query_aggs;

    protected iterable|BaseCollection|LazyCollection $aggregations;

    protected null|array|Elasticsearch|Promise $promiseResponse;

    public function __construct(
        callable $source,
        callable $total = null,
        callable $aggregations = null,
        null|array|Elasticsearch|Promise $response = null,
        protected null|Builder|EloquentBuilder $query = null,
        string $model = null,
    ) {
        $this->source = fn (): \Generator => yield from $source($response);
        $this->get_total = $total;
        $this->get_hits = $source;
        $this->get_aggregations = $aggregations;

        $this->promiseResponse = $response;

        if ($this->query instanceof ModelBuilder) {
            $this->with = $this->query?->getWith() ?? [];
        }

        // $this->model = $this->query?->getModel() ?? $model;

        $this->query_aggs = $this->query?->getAggregations() ?? new BaseCollection();

        // $this->source = $this->hits();
    }

    public function hits(): Collection
    {
        return $this->items ??= Collection::make(fn () => yield from ($this->get_hits)($this->response()))
            ->when(
                !empty($this->model),
                fn ($hits) => $hits
                    ->map(fn ($hit): DataAccessObject|Model => $this->model::unserialise($hit))
                    ->when(!empty($this->with), fn ($collection) => $collection->load($this->with))
            )->when(empty($this->model), fn ($hits) => $hits)
        ;
    }

    public function resetItems(array $items)
    {
        $this->source = fn () => yield from $items;

        return $this;
    }

    public function total(): int
    {
        return $this->total ??= ($this->get_total)($this->response());
    }

    public function aggregations(): LazyCollection|BaseCollection
    {
        return $this->aggregations ??= LazyCollection::make(function () {
            return $this->query_aggs->map(fn ($agg): AggregationResponse => $agg->toResponse(response: ($this->get_aggregations)($this->response())[$agg->getName()]));
        })

        ;
    }

    public function aggregation(string $name): null|AggregationResponse
    {
        return $this->aggregations()->get($name);
    }

    // Resolve and allow for serialisation
    public function wait($unwarp = true)
    {
        try {
            $this->response();

            $response = new Response(
                items: $this->hits(),
                total: $this->total(),
                aggregations: $this->aggregations()->collect(),
                query: $this->query,
                response: $this->response(),
            );

            // dd($response);
            if (!empty($this->onFulfilled)) {
                ($this->onFulfilled)($response);
            }
        } catch (\Throwable $e) {
            if (!empty($this->onRejected)) {
                ($this->onRejected)($e);
            }
            dd($e);
        }

        unset(
            $this->get_aggregations,
            $this->get_hits,
            $this->get_total,
            $this->query,
            $this->response,
            $this->onFulfilled,
            $this->onRejected,
        );

        // response(($this))->send();

        return $response ?? null;
    }

    public function response(): array
    {
        if (empty($this->response)) {
            if ($this->promiseResponse instanceof Promise) {
                return $this->response = $this->promiseResponse->wait()->asArray();
            }
            if ($this->promiseResponse instanceof Elasticsearch) {
                return $this->response = json_decode((string) $this->promiseResponse->getBody(), true);
            }

            return $this->response = $this->promiseResponse;
        }

        return $this->response;
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
    {
        $this->onFulfilled = $onFulfilled;
        $this->onRejected = $onRejected;
    }
}
