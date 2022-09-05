<?php

namespace Elastico\Query\Response;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Models\Builder\Builder as ModelBuilder;
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

     protected int $total;

     protected array $with;

     protected $onFulfilled;
     protected $onRejected;

     protected Collection $hits;

     protected BaseCollection $query_aggs;

     protected BaseCollection|LazyCollection $aggregations;

     protected array|Elasticsearch|Promise $promiseResponse;

     public function __construct(
         callable $total,
         callable $hits,
         callable $aggregations,
         array|Elasticsearch|Promise $response,
         protected null|Builder $query = null,
         string $model = null,
     ) {
         $this->get_total = $total;
         $this->get_hits = $hits;
         $this->get_aggregations = $aggregations;

         $this->promiseResponse = $response;

         if ($this->query instanceof ModelBuilder) {
             $this->with = $this->query?->getWith() ?? [];
         }
         $this->model = $this->query?->model ?? $model;
         $this->query_aggs = $this->query?->getAggregations() ?? new BaseCollection();
     }

     public function hits(): Collection
     {
         return $this->hits ??= Collection::make(fn () => yield from ($this->get_hits)($this->response()))
             ->when(
                 !empty($this->model),
                 fn ($hits) => $hits
                     ->map(fn ($hit): DataAccessObject|Model => $this->model::unserialise($hit))
                     ->when(!empty($this->with), fn ($a) => $a->load($this->with))
             )
         ;
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
                 total: $this->total(),
                 hits: $this->hits(),
                 aggregations: $this->aggregations(),
                 query: $this->query,
                 response: $this->response(),
             );

             if (!empty($this->onFulfilled)) {
                 ($this->onFulfilled)($response);
             }

             return $response;
         } catch (\Throwable $e) {
             if (!empty($this->onRejected)) {
                 ($this->onRejected)($e);
             }
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
     }

     public function response(): array
     {
         if (empty($this->response)) {
             if ($this->promiseResponse instanceof Promise) {
                 $this->response = json_decode((string) $this->promiseResponse->wait()->getBody(), true);
             } elseif ($this->promiseResponse instanceof Elasticsearch) {
                 $this->response = json_decode((string) $this->promiseResponse->getBody(), true);
             } else {
                 $this->response = $this->promiseResponse;
             }
         }

         return $this->response;
     }

     public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
     {
         $this->onFulfilled = $onFulfilled;
         $this->onRejected = $onRejected;
     }

     public function dd(): never
     {
         if (request()->wantsJson()) {
             // response($this->response())->send();
             // response(serialize($this->aggregations()))->send();
         }
         dd($this);
     }
 }
