<?php

namespace Elastico\Query\Response;

use Elastico\Query\Builder;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;

 /**
  * Elastic Base Response.
  */
 class Response
 {
     protected array $with;

     protected BaseCollection $query_aggs;

     public function __construct(
         protected int $total,
         protected Collection $hits,
         protected BaseCollection|LazyCollection $aggregations,
         protected array $response,
         protected null|Builder $query = null,
     ) {
         $this->with = $this->query?->getWith() ?? [];
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
         return $this->aggregations
         ;
     }

     public function aggregation(string $name): null|AggregationResponse
     {
         return $this->aggregations()->get($name);
     }

     public function response(): array
     {
         return $this->response;
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
