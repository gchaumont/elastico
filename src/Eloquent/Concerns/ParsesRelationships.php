<?php

namespace Elastico\Eloquent\Concerns;


use Illuminate\Support\Str;
use Elastico\Relations\ElasticRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;


trait ParsesRelationships
{


    public function partitionEloquentElasticRelationships(Model $model, array $relations): array
    {
        $partitioned = [
            'elastic' => [],
            'eloquent' => []
        ];

        // dump($relations);

        foreach ($relations as $key => $relation) {
            $original_relation = $relation;

            if (is_numeric($key)) {
                $relation_name = $relation;
                // $original_relation = fn ($a) => $a;
            } else {
                $relation_name = $key;
            }

            // $relation_name = is_array($relation) ? $relation[0] : $relation;

            // if (!is_string($relation_name)) {
            //     $relation_name = $key;
            // }

            $relation_id = Str::before($relation_name, ' as ');
            $as_name = Str::after($relation_name, ' as ') ?: $relation_name;

            $relation = $model->newQueryWithoutRelationships()->getRelation($relation_id);

            if ($relation instanceof ElasticRelation) {
                $partitioned['elastic'][$key] = $original_relation;
            } elseif ($relation instanceof EloquentRelation) {
                $partitioned['eloquent'][$key] =  $original_relation;
            } else {
                throw new \Exception('Invalid relation type');
            }
        }

        return $partitioned;
    }
}
