<?php

namespace Elastico\Query\Builder;

use Elastico\Query\Response\Collection;
use Illuminate\Support\Arr;
use Elastico\Scripting\Script;

/** 
 * @mixin \Elastico\Query\Builder
 */
trait PerformsBulkOperations
{
    // Available Elastic Bulk operations: create, index, update, delete 

    public function createMany(iterable $models): Collection
    {
        return $this->connection->bulk(
            $this->grammar->compileBulkOperation(
                query: $this,
                models: $models,
                operation: 'create'
            )
        );
    }

    /**
     * Equivalent of upsert
     */
    public function saveMany(iterable $models, array|Script $scripts = null): Collection
    {
        $scripts = is_array($scripts) ? $scripts : collect($models)->map(fn (array $model): Script => (clone $scripts)->withModel($model))->all();

        return $this->connection->bulk(
            $this->grammar->compileBulkOperation(
                query: $this,
                models: $models,
                operation: 'update',
                scripts: $scripts,
                doc_as_upsert: empty($scripts),
                scripted_upsert: !empty($scripts),
            )
        );
    }

    public function updateMany(iterable $models, array|Script $scripts = null): Collection
    {
        $scripts = is_array($scripts) ? $scripts : collect($models)->map(fn (array $model): Script => (clone $scripts)->withModel($model))->all();

        return $this->connection->bulk(
            $this->grammar->compileBulkOperation(
                query: $this,
                models: $models,
                operation: 'update',
                scripts: $scripts,
                doc_as_upsert: false,
                scripted_upsert: false,
            )
        );
    }

    public function deleteMany(iterable $ids): Collection
    {
        return $this->connection->bulk(
            $this->grammar->compileBulkOperation(
                query: $this,
                models: $ids,
                operation: 'delete'
            )
        );
    }
}
