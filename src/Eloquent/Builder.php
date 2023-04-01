<?php

namespace Elastico\Eloquent;

use Elastico\Eloquent\Concerns\QueriesRelationships;
use Elastico\Query\Builder as BaseBuilder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\Paginator;

/**
 *  Elasticsearch Query Builder
 *  Extension of Larvel Database Eloquent Builder.
 */
class Builder extends EloquentBuilder
{
    use QueriesRelationships;

    protected $passthru = [
        'aggregate',
        'average',
        'avg',
        'count',
        'dd',
        'doesntExist',
        'doesntExistOr',
        'dump',
        'exists',
        'existsOr',
        'explain',
        'getBindings',
        'getConnection',
        'getGrammar',
        'implode',
        // 'insert',
        'insertGetId',
        'insertOrIgnore',
        'insertUsing',
        'max',
        'min',
        'raw',
        'rawValue',
        'sum',
        'toSql',
        'getAggregations',
        'getMany',
        'enumerateTerms',
        'enumerate',
    ];

    public function __construct(BaseBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Find a model by its primary key.
     *
     * @param mixed        $id
     * @param array|string $columns
     *
     * @return null|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|static|static[]
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->model->hydrate(
            array_filter([$this->toBase()->find($id, $columns)])
        )->first();

        return $this->whereKey($id)->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param array|\Illuminate\Contracts\Support\Arrayable $ids
     * @param array|string                                  $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->model->hydrate(
            $this->toBase()->findMany($ids, $columns)->all()
        );
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|string $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $response = $this->query->get($columns);

        $models = $this->model->hydrate($response->all())->all();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $response->resetItems($models);
        // return $builder->getModel()->newCollection($models);
    }

    public function getModels($columns = ['*'])
    {
        return $this->model->hydrate(
            $this->query->get($columns)->all()
        )->all();
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array|string $uniqueBy
     * @param null|array   $update
     *
     * @return int
     */
    public function upsert(array $values, $uniqueBy = '_id', $update = null)
    {
        if (empty($values)) {
            return 0;
        }
        if ('_id' !== $uniqueBy) {
            throw new \InvalidArgumentException('Elastic only supports upserts by _id');
        }

        $values = collect($values)
            ->map(fn ($value) => $value instanceof Model ? ['_id' => $value->getKey(), '_index' => $value->getTable(), ...$value->getAttributes()] : $value)
            ->all()
        ;

        // if (!is_array(reset($values))) {
        //     $values = collect($values)
        //     ;
        // }
        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        return $this->toBase()->upsert(
            $this->addTimestampsToUpsertValues($values),
            $uniqueBy,
            $this->addUpdatedAtToUpsertColumns($update)
        );
    }

        public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
        {
            $page = $page ?: Paginator::resolveCurrentPage($pageName);

            $total = $this->toBase()->getCountForPagination();

            $perPage = (
                $perPage instanceof \Closure
                ? $perPage($total)
                : $perPage
            ) ?: $this->model->getPerPage();

            // Query anyway because aggregations
            $results = $this->forPage($page, $perPage)->get($columns);

            return $this->paginator($results, $total, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]);
        }

    /**
     * Get a lazy collection for the given query.
     *
     * @param mixed $keepAlive
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function cursor($keepAlive = '1m')
    {
        return $this->applyScopes()->query->cursor(keepAlive: $keepAlive)->map(function ($record) {
            return $this->newModelInstance()->newFromBuilder($record);
        });
    }

    public function insert(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        $values = collect($values)
            ->map(fn ($value) => $value instanceof Model ? ['_id' => $value->getKey(), '_index' => $value->getTable(), ...$value->getAttributes()] : $value)
            ->all()
        ;

        return $this->toBase()->insert(
            $values
        );
    }

    /**
     * Add the "updated at" column to an array of values.
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (!$this->model->usesTimestamps()
            || is_null($this->model->getUpdatedAtColumn())) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        return array_merge(
            [$column => $this->model->freshTimestampString()],
            $values
        );
    }
}
