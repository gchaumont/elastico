<?php

namespace Elastico\Eloquent\Relations;

use Closure;
use InvalidArgumentException;
use Elastico\Aggregations\Bucket\Terms;
use Illuminate\Database\Eloquent\Model;
use Elastico\Aggregations\Metric\TopHits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Elastico\Query\Response\Aggregation\BucketResponse;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;

class HasOne extends EloquentHasOne implements ElasticRelation
{
    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getForeignKeyName();

        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param string $key
     *
     * @return string
     */
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }



    /**
     * Indicate that the relation is a single result of a larger one-to-many relationship.
     *
     * @param  string|array|null  $column
     * @param  string|\Closure|null  $aggregate
     * @param  string|null  $relation
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function ofMany($column = 'id', $aggregate = 'MAX', $relation = null)
    {

        $this->isOneOfMany = true;

        $this->relationName = $relation ?: $this->getDefaultOneOfManyJoinAlias(
            $this->guessRelationship()
        );

        $keyName = $this->query->getModel()->getKeyName();

        $columns = is_string($columns = $column) ? [
            $column => $aggregate,
            $keyName => $aggregate,
        ] : $column;

        if (!array_key_exists($keyName, $columns)) {
            $columns[$keyName] = 'MAX';
        }

        if ($aggregate instanceof Closure) {
            $closure = $aggregate;
        }

        $columns = array_slice($columns, 0, 1);
        foreach ($columns as $column => $aggregate) {
            if (!in_array(strtolower($aggregate), ['min', 'max'])) {
                throw new InvalidArgumentException("Invalid aggregate [{$aggregate}] used within ofMany relation. Available aggregates: MIN, MAX");
            }

            $this->addAggregation(
                $this->relationName,
                (new Terms(field: $this->foreignKey, size: 1000))
                    ->addAggregation(
                        'hits',
                        new TopHits(
                            size: 1,
                            from: 0,
                            model: get_class($this->getmodel()),
                            sort: [[
                                $column => [
                                    'order' => $aggregate == 'MAX' ? 'desc' : 'asc',
                                ],
                            ]],
                            _source: $this->query->getQuery()->columns,
                        )
                    )
            );
        }

        $this->take(0);

        return  $this;
    }


    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        if ($this->isOneOfMany()) {
            $results = $results
                ->aggregation($this->relationName)
                ->buckets()
                ->map(static fn (BucketResponse $bucket) => $bucket->aggregation('hits')->hits()->first())
                ->keyBy(static fn (Model $model) => $model->getKey())
                ->filter()
                ->pipe(static fn (BaseCollection $collection) => $collection->first()->newCollection($collection->all()));
        }

        return $this->matchOne($models, $results, $relation);
    }
}
