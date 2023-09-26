<?php

namespace Elastico\Eloquent;

use Illuminate\Contracts\Support\Arrayable;

/**
 *  Elasticsearch Cached Query Builder
 * 
 * @mixin \Elastico\Eloquent\Builder
 */
class CachedBuilder extends Builder
{

    /**
     * Find a model by its primary key.
     *
     * @param mixed        $id
     * @param array|string $columns
     *
     * @return null|\Elastico\Query\Response\Collection|\Illuminate\Database\Eloquent\Model|static|static[]
     */
    public function find($id, $columns = ['*'])
    {
        $columns = $this->getColumnNames($columns);

        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->model->hydrate(
            [$this->toBase()->find($id, $columns)]
        )->first();

        return $this->whereKey($id)->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param array|\Illuminate\Contracts\Support\Arrayable $ids
     * @param array|string                                  $columns
     *
     * @return \Elastico\Query\Response\Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        $columns = $this->getColumnNames($columns);

        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        $models =  $this->model->hydrate(
            $this->toBase()->findMany($ids, $columns)->all()
        );

        // Add eagerloading because we use findMany to load relations more efficiently with whereKey
        // also it's just useful to have eagerloading on findMany
        if (count($models) > 0) {
            $items = $this->eagerLoadRelations($models->all());
            $items = $this->eagerLoadAggregations($items);
            $models->resetItems($items);
        }

        return $models;
    }
}
