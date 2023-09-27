<?php

namespace Elastico\Eloquent;

use Illuminate\Support\Arr;
use Illuminate\Pagination\Paginator;
use Elastico\Query\Builder as BaseBuilder;
use Illuminate\Contracts\Support\Arrayable;
use Elastico\Eloquent\Concerns\LoadsAggregates;
use Elastico\Eloquent\Concerns\QueriesRelationships;
use Elastico\Query\Response\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\LazyCollection;

/**
 *  Elasticsearch Query Builder
 *  Extension of Larvel Database Eloquent Builder.
 * 
 * @mixin \Elastico\Query\Builder
 */
class Builder extends EloquentBuilder
{
    use QueriesRelationships;
    use LoadsAggregates;

    const DEFAULT_CHUNK_SIZE = 1000;

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
        'deleteMany'
    ];

    /**
     * The base query builder instance.
     *
     * @var BaseBuilder $query
     */
    protected $query;

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
     * @return null|\Elastico\Query\Response\Collection|\Illuminate\Database\Eloquent\Model|static|static[]
     */
    public function find($id, $columns = ['*'])
    {
        $this->passColumnNamesToQuery($columns);

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
        $this->passColumnNamesToQuery($columns);

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

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|string $columns
     *
     * @return \Elastico\Query\Response\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $this->passColumnNamesToQuery($columns);

        $response = $this->query->get($columns);

        $models = $this->model->hydrate($response->all())->all();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
            $models = $builder->eagerLoadAggregations($models);
        }


        return $response->resetItems($models);
    }

    public function whereKey($id)
    {
        $query = parent::whereKey($id);

        if (is_countable($id)) {
            $query->limit(count($id));
        }
        return $query;
    }


    public function getModels($columns = ['*'])
    {
        $this->passColumnNamesToQuery($columns);

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
            ->reject(fn ($value) => $value instanceof Model && !$value->isDirty())
            ->map(fn ($value) => $value instanceof Model ? ['_id' => $value->getKey(), '_index' => $value->getTable(), ...$value->getDirty()] : $value)
            ->all();

        if (empty($values)) {
            return 0;
        }
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

        $this->passColumnNamesToQuery($columns);

        $perPage = ($perPage instanceof \Closure
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
        if (empty($this->query->columns) || $this->query->columns === ['*']) {
            $this->select($this->getModel()->getFieldNames());
        }
        return $this->applyScopes()->query->cursor(keepAlive: $keepAlive)
            ->map(function ($record): Model {
                foreach ($this->query->columns as $column) {
                    $record['_source'][$column] ??= null;
                }

                return $this->newModelInstance()->newFromBuilder($record);
            })
            ->chunk($this->query->limit ?? static::DEFAULT_CHUNK_SIZE)
            ->map(fn (LazyCollection $models): array => $models->all())
            ->map(fn (array $models): array => $this->eagerLoadRelations($models))
            ->map(fn (array $models): array => $this->eagerLoadAggregations($models))
            ->collapse();
    }

    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public function delete($id = null)
    {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        if (!is_null($id)) {
            return $this->toBase()->delete($id);
        }

        return $this->toBase()->delete();
    }

    public function insert(array $values)
    {
        if (empty($values)) {
            return 0;
        }
        $insertValues = collect($values)
            ->map(fn ($value) => $value instanceof Model ? [
                '_id' => $value->getKey(),
                '_index' => $value->getTable(),
                ...$value->getAttributes()
            ] : $value)
            ->all();

        $response = $this->toBase()->insert(
            $insertValues
        );

        # if values are only models 
        if (isset($values[0]) && $values[0] instanceof Model) {

            return collect($values)
                ->each(function (Model $value, int $i) use ($response): void {
                    $value->exists = true;
                    $value->setAttribute($value->getKeyName(), $response['items'][$i]['create']['_id']);
                    $value->_id = $response['items'][$i]['create']['_id'];
                    $value->_index = $response['items'][$i]['create']['_index'];
                })
                ->pipe(fn ($values) => $values->first()->newCollection($values->all()));
        }

        return $response;
    }

    /**
     * Add the "updated at" column to an array of values.
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (
            !$this->model->usesTimestamps()
            || is_null($this->model->getUpdatedAtColumn())
        ) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        return array_merge(
            [$column => $this->model->freshTimestampString()],
            $values
        );
    }

    protected function passColumnNamesToQuery(string|array $columns): void
    {
        // return $columns;
        // return Arr::wrap($columns);
        $columns = Arr::wrap($columns);
        if (empty($columns) || count($columns) === 1 && $columns[0] === '*') {
            $this->getQuery()->setRequestedColumns($this->getModel()->getFieldNames());
        }
    }


    /**
     * Add a basic where clause to a relationship query.
     *
     * @param  string  $relation
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function whereRelation($relation, $column, $operator = null, $value = null)
    {
        return $this->where($relation . '.' . $column, $operator, $value);

        // return $this->whereHas($relation, function ($query) use ($column, $operator, $value) {
        //     if ($column instanceof Closure) {
        //         $column($query);
        //     } else {
        //         $query->where($column, $operator, $value);
        //     }
        // });
    }
}
