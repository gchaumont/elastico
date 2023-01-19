<?php

namespace Elastico\Query;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Aggregations\Metric\Avg;
use Elastico\Aggregations\Metric\Max;
use Elastico\Aggregations\Metric\Min;
use Elastico\Aggregations\Metric\Sum;
use Elastico\Connection;
use Elastico\Query\Builder\HasAggregations;
use Elastico\Query\Builder\HasPostFilter;
use Elastico\Query\Compound\Boolean;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

/**
 *  Elasticsearch Query Builder
 *  Extension of Larvel Database Query Builder.
 */
class Builder extends BaseBuilder
{
    use HasAggregations;
    use HasPostFilter;

    // TODO: Update all functions referring to "Grammar"

    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
        'rank',
    ];

    /**
     * All of the available bitwise operators.
     *
     * @var string[]
     */
    public $bitwiseOperators = [
        // '&', '|', '^', '<<', '>>', '&~',
    ];

    public $model_id;

    public $collapse;

    /**
     * Explains the query.
     *
     * @return \Illuminate\Support\Collection
     */
    public function explain()
    {
        throw new Exception('Not Supported');
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|string $columns
     *
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        return $this->onceWithColumns(Arr::wrap($columns), function () {
            return $this->processor->processSelect($this, $this->runSelect());
        });
    }

    public function getMany(iterable $queries)
    {
        return $this->processor->processSelectMany(
            collect($queries)->all(),
            $this->connection->selectMany(
                $this->grammar->compileSelectMany($queries),
            )
        );
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param int|string   $id
     * @param array|string $columns
     *
     * @return mixed|static
     */
    public function find($id, $columns = ['*'])
    {
        return $this->processor->processFind(
            $this,
            $this->connection->find(
                $this->grammar->compileGet($this->from, $id, $columns)
            )
        );
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param int|string   $id
     * @param array|string $columns
     * @param mixed        $ids
     *
     * @return mixed|static
     */
    public function findMany($ids, $columns = ['*'])
    {
        return $this->processor->processFindMany(
            $this,
            $this->connection->findMany(
                $this->grammar->compileFindMany($this->from, $ids, $columns)
            )
        );
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        $this->applyBeforeQueryCallbacks();

        return $this->grammar->compileSelect($this);
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param array $columns
     *
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        return $this->cloneWithout(['columns', 'orders', 'limit', 'offset'])->count();
        $results = $this->runPaginationCountQuery($columns);

        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        if (!isset($results[0])) {
            return 0;
        }
        if (is_object($results[0])) {
            return (int) $results[0]->aggregate;
        }

        return (int) array_change_key_case((array) $results[0])['aggregate'];
    }

    /**
     * Get a lazy collection for the given query.
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }

        return new LazyCollection(function () {
            yield from $this->connection->cursor(
                $this->grammar->compileSelect($this),
            );
        });
    }

    /**
     * Get a collection instance containing the values of a given column.
     *
     * @param string      $column
     * @param null|string $key
     *
     * @return \Illuminate\Support\Collection
     */
    public function pluck($column, $key = null)
    {
        // First, we will need to select the results of the query accounting for the
        // given columns / key. Once we have the results, we will be able to take
        // the results and get the exact data that was requested for the query.
        $queryResult = $this->onceWithColumns(
            is_null($key) ? [$column] : [$column, $key],
            function () {
                return $this->processor->processSelect(
                    $this,
                    $this->runSelect()
                );
            }
        );

        if (empty($queryResult)) {
            return collect();
        }

        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        $column = $this->stripTableForPluck($column);

        $key = $this->stripTableForPluck($key);

        $queryResult = $queryResult->map(fn ($r) => $r['_source']);

        return is_array($queryResult->first())
                    ? $this->pluckFromArrayColumn($queryResult, $column, $key)
                    : $this->pluckFromObjectColumn($queryResult, $column, $key);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param array|\Closure|string $column
     * @param mixed                 $operator
     * @param mixed                 $value
     * @param string                $boolean
     * @param null|mixed            $boost
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            2 === func_num_args()
        );

        // If the column is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parentheses.
        // We will add that Closure to the query and return back out immediately.
        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }

        // If the column is a Closure instance and there is an operator value, we will
        // assume the developer wants to run a subquery and then compare the result
        // of that subquery with the given value that was provided to the method.
        if ($this->isQueryable($column) && !is_null($operator)) {
            [$sub, $bindings] = $this->createSub($column);

            return $this->addBinding($bindings, 'where')
                ->where(new Expression('('.$sub.')'), $operator, $value, $boolean)
            ;
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, '=' !== $operator);
        }

        $type = 'Basic';

        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        if (str_contains($column, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');

            if (is_string($column)) {
                $type = 'JsonBoolean';
            }
        }

        // if ($this->isBitwiseOperator($operator)) {
        //     $type = 'Bitwise';
        // }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'value',
            'boolean'
        );

        // if (!$value instanceof Expression) {
        //     $this->addBinding($this->flattenValue($value), 'where');
        // }

        return $this;
    }

    public function rank(string $field, int|float $boost = null): self
    {
        return $this->where($field, 'rank', $boost);
    }

    public function collapse(string|array $collapse): self
    {
        if (is_string($collapse)) {
            $this->collapse = ['field' => $collapse];
        } else {
            $this->collapse = $collapse;
        }

        return $this;
    }

    /**
     * Create a raw database expression.
     *
     * @param mixed $value
     *
     * @return \Illuminate\Database\Query\Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * Insert new records into the database.
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->bulk(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert new records into the database while ignoring errors.
     *
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertOrIgnore($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param null|string $sequence
     *
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    /**
     * Insert new records into the table using a subquery.
     *
     * @param \Closure|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|string $query
     *
     * @return int
     */
    public function insertUsing(array $columns, $query)
    {
        $this->applyBeforeQueryCallbacks();

        [$sql, $bindings] = $this->createSub($query);

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertUsing($this, $columns, $sql),
            $this->cleanBindings($bindings)
        );
    }

    /**
     * Update records in the database.
     *
     * @return int
     */
    public function update(array $values)
    {
        $this->applyBeforeQueryCallbacks();

        return $this->connection->update(
            $this->grammar->compileUpdate($this, $values)
        );
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param string    $column
     * @param float|int $amount
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("{$wrapped} + {$amount}")], $extra);

        return $this->update($columns);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param string    $column
     * @param float|int $amount
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("{$wrapped} - {$amount}")], $extra);

        return $this->update($columns);
    }

    /**
     * Delete records from the database.
     *
     * @param mixed $id
     *
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->connection->deleteDocument($id, $this->from);
            // $this->where($this->from.'.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->delete(
            $this->grammar->compileDelete($this),
        );
    }

    /**
     * Run a truncate statement on the table.
     */
    public function truncate()
    {
        $this->applyBeforeQueryCallbacks();

        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array|string $uniqueBy
     * @param null|array   $update
     *
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (empty($values)) {
            return 0;
        }
        if ([] === $update) {
            return (int) $this->insert($values);
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $this->applyBeforeQueryCallbacks();

        $bindings = $this->cleanBindings(array_merge(
            Arr::flatten($values, 1),
            collect($update)->reject(function ($value, $key) {
                return is_int($key);
            })->all()
        ));

        $response = $this->connection->bulk(
            $this->grammar->compileUpsert($this, $values, (array) $uniqueBy, $update),
            $bindings
        );

        if ($response['errors']) {
            throw new \RuntimeException('Error inserting documents');
        }

        return $response;
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $columns
     *
     * @return int
     */
    public function count($columns = '*')
    {
        return $this->processor->resolvePromise(
            $this->connection->count($this->grammar->compileCount($this))
        )['count'];
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param string $function
     * @param array  $columns
     *
     * @return mixed
     */
    public function aggregate($function, $columns = ['*'])
    {
        return $this->take(0)
            ->setAggregations(
                collect([(match ($function) {
                    'sum' => new Sum('agg'),
                    'avg' => new Avg('agg'),
                    'min' => new Min('agg'),
                    'max' => new Max('agg'),
                })->field($columns[0])])
            )
            ->get()
            ->aggregation('agg')
            ->value()
        ;
    }

    /**
     * Put the query's results in random order.
     *
     * @param string $seed
     *
     * @return $this
     */
    public function inRandomOrder($seed = '')
    {
        return $this->beforeQuery(function ($query) use ($seed) {
            return $query->whereRaw($this->grammar->compileRandom($seed));
        });
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        $this->applyBeforeQueryCallbacks();

        $results = $this->connection->select(
            $this->grammar->compileExists($this),
            $this->getBindings(),
            !$this->useWritePdo
        );

        return $results->total() > 0;
    }

    public function enumerateTerms(string $column): array
    {
        return $this->connection->termsEnum($this->from, $column)['terms'];
    }

    public function enumerate(
        string $field,
        string $string = null,
        string $after = null,
        int $size = 10,
        bool $insensitive = true
    ): LazyCollection {
        return LazyCollection::make(function () use ($field, $string, $after, $size, $insensitive): \Generator {
            do {
                $response = $this->connection->termsEnum(
                    index: $this->from,
                    field: $field,
                    size: $size,
                    string: $string,
                    after : $after,
                    insensitive: $insensitive,
                );

                if ($response instanceof Promise) {
                    $response = $response->wait()->asArray();
                }
                if ($response instanceof Elasticsearch) {
                    $response = $response->asArray();
                }
                foreach ($response['terms'] as $term) {
                    yield $term;
                }

                $after = end($response['terms']);
            } while ($size == count($response['terms']));
        });
    }

        public function orderBy(
            $column,
            $direction = 'asc',
            string $missing = null,
            string $mode = null,
            array $nested = null
        ) {
            // if ($this->isQueryable($column)) {
            //     [$query, $bindings] = $this->createSub($column);

            //     $column = new Expression('('.$query.')');

            //     $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');
            // }

            $direction = strtolower($direction);

            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new \InvalidArgumentException('Order direction must be "asc" or "desc".');
            }

            $this->orders[] = compact('column', 'direction', 'missing', 'mode', 'nested');

            return $this;
        }

    /**
     * Strip off the table name or alias from a column identifier.
     *
     * @param string $column
     *
     * @return null|string
     */
    protected function stripTableForPluck($column)
    {
        if (is_null($column)) {
            return $column;
        }

        $separator = str_contains(strtolower($column), ' as ') ? ' as ' : '\.';

        return last(preg_split('~'.$separator.'~i', $column));
    }

    /**
     * Run a pagination count query.
     *
     * @param array $columns
     *
     * @return array
     */
    protected function runPaginationCountQuery($columns = ['*'])
    {
        if ($this->groups || $this->havings) {
            $clone = $this->cloneForPaginationCount();

            if (is_null($clone->columns) && !empty($this->joins)) {
                $clone->select($this->from.'.*');
            }

            return $this->newQuery()
                ->from(new Expression('('.$clone->toSql().') as '.$this->grammar->wrap('aggregate_table')))
                ->mergeBindings($clone)
                ->setAggregate('count', $this->withoutSelectAliases($columns))
                ->get()->all();
        }

        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];
        // dd($this->cloneWithout($without)
        //     ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
        //     ->setAggregate('count', $this->withoutSelectAliases($columns)));

        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get()->all();
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        return $this->connection->select(
            $this->toSql(),
            $this->getBindings(),
            !$this->useWritePdo
        );
    }

    /**
     * Paginate the given query using a cursor paginator.
     *
     * @param int                                       $perPage
     * @param array|string                              $columns
     * @param string                                    $cursorName
     * @param null|\Illuminate\Pagination\Cursor|string $cursor
     *
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    protected function paginateUsingCursor($perPage, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        if (!$cursor instanceof Cursor) {
            $cursor = is_string($cursor)
                ? Cursor::fromEncoded($cursor)
                : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
        }

        $orders = $this->ensureOrderForCursorPagination(!is_null($cursor) && $cursor->pointsToPreviousItems());

        if (!is_null($cursor)) {
            $addCursorConditions = function (self $builder, $previousColumn, $i) use (&$addCursorConditions, $cursor, $orders) {
                $unionBuilders = isset($builder->unions) ? collect($builder->unions)->pluck('query') : collect();

                if (!is_null($previousColumn)) {
                    $originalColumn = $this->getOriginalColumnNameForCursorPagination($this, $previousColumn);

                    $builder->where(
                        Str::contains($originalColumn, ['(', ')']) ? new Expression($originalColumn) : $originalColumn,
                        '=',
                        $cursor->parameter($previousColumn)
                    );

                    $unionBuilders->each(function ($unionBuilder) use ($previousColumn, $cursor) {
                        $unionBuilder->where(
                            $this->getOriginalColumnNameForCursorPagination($this, $previousColumn),
                            '=',
                            $cursor->parameter($previousColumn)
                        );

                        $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                    });
                }

                $builder->where(function (self $builder) use ($addCursorConditions, $cursor, $orders, $i, $unionBuilders) {
                    ['column' => $column, 'direction' => $direction] = $orders[$i];

                    $originalColumn = $this->getOriginalColumnNameForCursorPagination($this, $column);

                    $builder->where(
                        Str::contains($originalColumn, ['(', ')']) ? new Expression($originalColumn) : $originalColumn,
                        'asc' === $direction ? '>' : '<',
                        $cursor->parameter($column)
                    );

                    if ($i < $orders->count() - 1) {
                        $builder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i) {
                            $addCursorConditions($builder, $column, $i + 1);
                        });
                    }

                    $unionBuilders->each(function ($unionBuilder) use ($column, $direction, $cursor, $i, $orders, $addCursorConditions) {
                        $unionBuilder->where(function ($unionBuilder) use ($column, $direction, $cursor, $i, $orders, $addCursorConditions) {
                            $unionBuilder->where(
                                $this->getOriginalColumnNameForCursorPagination($this, $column),
                                'asc' === $direction ? '>' : '<',
                                $cursor->parameter($column)
                            );

                            if ($i < $orders->count() - 1) {
                                $unionBuilder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i) {
                                    $addCursorConditions($builder, $column, $i + 1);
                                });
                            }

                            $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                        });
                    });
                });
            };

            $addCursorConditions($this, null, 0);
        }

        $this->limit($perPage + 1);

        return $this->cursorPaginator($this->get($columns), $perPage, $cursor, [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => $orders->pluck('column')->toArray(),
        ]);
    }

    /**
     * Get the original column name of the given column, without any aliasing.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $builder
     *
     * @return string
     */
    protected function getOriginalColumnNameForCursorPagination($builder, string $parameter)
    {
        $columns = $builder instanceof Builder ? $builder->getQuery()->columns : $builder->columns;

        if (!is_null($columns)) {
            foreach ($columns as $column) {
                if (($position = stripos($column, ' as ')) !== false) {
                    $as = substr($column, $position, 4);

                    [$original, $alias] = explode($as, $column);

                    if ($parameter === $alias || $builder->getGrammar()->wrap($parameter) === $alias) {
                        return $original;
                    }
                }
            }
        }

        return $parameter;
    }
}
