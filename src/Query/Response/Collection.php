<?php

namespace Elastico\Query\Response;

use Illuminate\Support\LazyCollection;

/**
  * Elastic Base Response.
  */
 class Collection extends LazyCollection
 {
     public function load(array|string $relations): static
     {
         if ($this->isEmpty()) {
             return $this;
         }
         $relations = is_string($relations) ? func_get_args() : $relations;

         $query = $this->first()->query()->with($relations);

         $this->source = $query->eagerLoadRelations($this->all());

         return $this;
         $static = $this;
         dd('asd');
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
                     ->map(fn ($hit) => $hit->{$propName}->getKey())
                     ->values()
            ;

                 if ($relatedIds->isEmpty()) {
                     continue;
                 }

                 $cachedModels[$relation] = collect(Cache::many(
                     collect($relatedIds)->map(fn ($id) => 'rel:'.$relation.'.'.$id)->unique()->values()->all()
                 ))
                     ->filter()
                     ->keyBy(fn ($m) => $m->getKey())
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
                         $models->keyBy(fn ($m) => 'rel:'.$relation.'.'.$m->getKey())->all(),
                         now()->addMinutes(60)
                     );
                 }

                 $models = $models->concat(collect($cachedModels[$relation]))->keyBy(fn ($m) => $m->getKey());

                 $propName = $static->first()->getPropertyNameForClass($relation);

                 $static = $static->map(function ($hit) use ($propName, $models) {
                     if (isset($hit->{$propName})) {
                         $id = $hit->{$propName}->getKey();
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
 }
