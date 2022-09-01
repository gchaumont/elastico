<?php

namespace Elastico\Query\Response;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;

 /**
  * Elastic Base Response.
  * // TODO: remove cache from here.
  */
 class Collection extends LazyCollection
 {
     public function load($relations): static
     {
         $static = $this;

         if (['*'] === $relations) {
             $relations = $static->first()::getAllRelations();
         }

         $relations = collect($relations);

         if ($relations->isNotEmpty() && $static->isNotEmpty()) {
             // SEPARATE RELATION LOADING
             $cachedModels = $relatedModels = [];

             foreach ($relations as $relation) {
                 $propName = $static->first()->getPropertyNameForClass($relation);

                 $relatedIds = $static
                     ->filter(fn ($hit) => isset($hit->{$propName}))
                     ->map(fn ($hit) => $hit->{$propName}->get_id())
                     ->values()
            ;

                 if ($relatedIds->isEmpty()) {
                     continue;
                 }

                 $cachedModels[$relation] = collect(Cache::many(
                     collect($relatedIds)->map(fn ($id) => 'rel:'.$relation.'.'.$id)->unique()->values()->all()
                 ))
                     ->filter()
                     ->keyBy(fn ($m) => $m->get_id())
                 ;

                 $remainingIds = $relatedIds->reject(fn ($id) => $cachedModels[$relation]->has($id));

                 if ($remainingIds->isEmpty()) {
                     $relatedModels[$relation] = collect();

                     continue;
                 }

                 $relatedModels[$relation] = $relation::query()->findMany($remainingIds);
             }

             foreach ($relatedModels as $relation => $models) {
                 if (!$models->isEmpty()) {
                     Cache::putMany(
                         $models->keyBy(fn ($m) => 'rel:'.$relation.'.'.$m->get_id())->all(),
                         now()->addMinutes(60)
                     );
                 }

                 $models = $models->concat(collect($cachedModels[$relation]))->keyBy(fn ($m) => $m->get_id());

                 $propName = $static->first()->getPropertyNameForClass($relation);

                 $static = $static->map(function ($hit) use ($propName, $models) {
                     if (isset($hit->{$propName})) {
                         $id = $hit->{$propName}->get_id();
                         if ($models->has($id)) {
                             $hit->{$propName} = $models->get($id);
                         }
                         // else {
                         //     if (request()->wantsJson()) {
                         //         response([$propName, $id,  $models->get($id)])->send();
                         //     }
                         // }
                         // $hit->_loaded_rel ??= [];
                         // $hit->_loaded_rel[$propName] = true;
                     }

                     return $hit;
                 });
             }
         }

         return $static;
     }

     public function loadRelated(string $model, string|callable $key, string|callable $property)
     {
         if (is_string($property)) {
             $property = fn ($o, $p) => $o->{$property} = $p;
         }
         if (is_string($key)) {
             $key = fn ($o) => $o->{$key};
         }

         $map = $this->keyBy(fn ($m) => $m->get_id())
             ->map($key)
         ;

         $related = $map
             ->filter()
             ->pipe(fn ($keys) => $model::query()->findMany($keys))
         ;

         return $this->map(function ($model) use ($property, $related, $map) {
             // if ($related->has($model->get_id())) {
             $property($model, $related->get($map->get($model->get_id())));
             // }

             return $model;
         })
        ;
     }

     public function loadAggregate()
     {
         // code...
     }

     public function loadCount()
     {
         // code...
     }

     public function loadMax($value = '')
     {
         // code...
     }

     public function loadMin()
     {
         // code...
     }

     public function loadSum($value = '')
     {
         // code...
     }

     public function loadAvg()
     {
         // code...
     }

     public function loadExist()
     {
         // code...
     }

     public function loadMissing()
     {
         // code...
     }
 }
