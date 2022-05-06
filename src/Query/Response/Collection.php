<?php

namespace Gchaumont\Query\Response;

use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;

 /**
  * Elastic Base Response.
  */
 class Collection extends BaseCollection
 {
     public function load($relations): static
     {
         if (['*'] === $relations) {
             $relations = $this->first()::getAllRelations();
         }

         $relations = collect($relations);

         if ($this->isNotEmpty()) {
             // SEPARATE RELATION LOADING
             $cachedModels = $relatedModels = [];

             foreach ($relations as $relation) {
                 $propName = $this->first()->getPropertyNameForClass($relation);

                 $relatedIds = $this
                     ->filter(fn ($hit) => isset($hit->{$propName}))
                     ->map(fn ($hit) => $hit->{$propName}->get_id())
                     ->values()
            ;

                 if ($relatedIds->isEmpty()) {
                     continue;
                 }

                 $cachedModels[$relation] = collect(Cache::many(
                     collect($relatedIds)->map(fn ($id) => 'rel:'.$relation.'.'.$id)->all()
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

                 $propName = $this->first()->getPropertyNameForClass($relation);

                 $this->transform(function ($hit) use ($propName, $models) {
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

         return $this;
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
