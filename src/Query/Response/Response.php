<?php

namespace Elastico\Query\Response;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Models\Model;
use Elastico\Query\Builder;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Http\Promise\Promise;
use Illuminate\Support\Collection as BaseCollection;

 /**
  * Elastic Base Response.
  */
 class Response
 {
     protected $get_total;

     protected $get_hits;

     protected $get_aggregations;

     protected int $total;

     protected bool $resolved = false;

     protected array $_response;

     protected Collection $hits;

     protected BaseCollection $aggregations;

     public function __construct(
         callable $total,
         callable $hits,
         callable $aggregations,
         protected array|Elasticsearch|Promise $response,
         protected Builder $query,
     ) {
         $this->get_total = $total;
         $this->get_hits = $hits;
         $this->get_aggregations = $aggregations;
     }

     public function hits(): Collection
     {
         return $this->hits ??= Collection::make(($this->get_hits)($this->response()))
             ->when(
                 !empty($this->query->searchableModel),
                 fn ($hits) => $hits
                     ->map(fn ($hit): Model => $this->query->searchableModel::unserialise($hit))
                     ->load($this->query->getWith())
             )
         ;
     }

     public function total(): int
     {
         return $this->total ??= ($this->get_total)($this->response());
     }

     public function aggregations(): BaseCollection
     {
         return $this->aggregations ??= $this->query
             ->getAggregations()
             ->map(fn ($agg): AggregationResponse => $agg->toResponse(
                 response: ($this->get_aggregations)($this->response())[$agg->getName()],
                 query: $this->query,
             ))
         ;
     }

     public function aggregation(string $name): null|AggregationResponse
     {
         return $this->aggregations()->get($name);
     }

     // Resolve and allow for serialisation
     public function wait(): static
     {
         $this->hits();
         $this->aggregations();
         $this->total();

         unset($this->get_aggregations, $this->get_hits, $this->get_total, $this->query);

         return $this;
     }

     public function response(): array
     {
         if ($this->response instanceof Promise) {
             $this->response = json_decode((string) $this->response->wait()->getBody(), true);
         } elseif ($this->response instanceof Elasticsearch) {
             $this->response = json_decode((string) $this->response->getBody(), true);
         } else {
             $this->response = $this->response;
         }

         return $this->response;
     }

     public function dd(): never
     {
         if (request()->wantsJson()) {
             response(serialize($this->aggregations))->send();
         }
         dd($this);
     }
 }
