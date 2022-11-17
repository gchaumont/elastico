<?php

namespace Elastico\Query\Response;

use Elastico\Models\Builder\Builder as ModelBuilder;
use Elastico\Query\Builder;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;

 /**
  * Elastic Base Response.
  */
 class Response extends Collection
 {
     protected array $with;

     protected BaseCollection $query_aggs;

     public function __construct(
         protected int $total,
         LazyCollection $hits,
         protected BaseCollection|LazyCollection $aggregations,
         protected array $response,
         protected null|Builder $query = null,
         string $model = null,
     ) {
         $this->hits = $hits;
         $this->source = $this->hits;

         if ($this->query instanceof ModelBuilder) {
             $this->with = $this->query?->getWith() ?? [];
         }
         $this->model = $this->query?->model ?? $model;
         $this->query_aggs = $this->query?->getAggregations() ?? new BaseCollection();
     }

     public function hits(): Collection
     {
         return $this->hits;
     }

     public function total(): int
     {
         return $this->total;
     }

     public function aggregations(): LazyCollection|BaseCollection
     {
         return $this->aggregations;
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
