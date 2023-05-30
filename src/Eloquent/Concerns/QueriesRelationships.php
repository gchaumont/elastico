<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Elastico\Query\Response\Aggregation\AggregationResponse;

trait QueriesRelationships
{


    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param Relation|string $relation
     * @param string          $operator
     * @param int             $count
     * @param string          $boolean
     *
     * @return Builder|static
     *
     * @throws \Exception
     */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', \Closure $callback = null)
    {
        if (is_string($relation)) {
            if (false !== strpos($relation, '.')) {
                return $this->hasNested($relation, $operator, $count, $boolean, $callback);
            }

            $relation = $this->getRelationWithoutConstraints($relation);
        }

        // If this is a hybrid relation then we can not use a normal whereExists() query that relies on a subquery
        // We need to use a `whereIn` query
        if ($this->getModel() instanceof Model || $this->isAcrossConnections($relation)) {
            return $this->addHybridHas($relation, $operator, $count, $boolean, $callback);
        }

        // If we only need to check for the existence of the relation, then we can optimize
        // the subquery to only run a "where exists" clause instead of this full "count"
        // clause. This will make these queries run much faster compared with a count.
        $method = $this->canUseExistsForExistenceCheck($operator, $count)
            ? 'getRelationExistenceQuery'
            : 'getRelationExistenceCountQuery';

        $hasQuery = $relation->{$method}(
            $relation->getRelated()->newQuery(),
            $this
        );

        // Next we will call any given callback as an "anonymous" scope so they can get the
        // proper logical grouping of the where clauses if needed by this Eloquent query
        // builder. Then, we will be ready to finalize and return this query instance.
        if ($callback) {
            $hasQuery->callScope($callback);
        }

        return $this->addHasWhere(
            $hasQuery,
            $relation,
            $operator,
            $count,
            $boolean
        );
    }

    /**
     * Compare across databases.
     *
     * @param string $operator
     * @param int    $count
     * @param string $boolean
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function addHybridHas(Relation $relation, $operator = '>=', $count = 1, $boolean = 'and', \Closure $callback = null)
    {
        $hasQuery = $relation->getQuery();
        if ($callback) {
            $hasQuery->callScope($callback);
        }

        // If the operator is <, <= or !=, we will use whereNotIn.
        $not = in_array($operator, ['<', '<=', '!=']);
        // If we are comparing to 0, we need an additional $not flip.
        if (0 == $count) {
            $not = !$not;
        }

        $relations = $hasQuery->pluck($this->getHasCompareKey($relation));

        $relatedIds = $this->getConstrainedRelatedIds($relations, $operator, $count);

        return $this->whereIn($this->getRelatedConstraintKey($relation), $relatedIds, $boolean, $not);
    }

    /**
     * @return bool
     */
    protected function isAcrossConnections(Relation $relation)
    {
        return $relation->getParent()->getConnectionName() !== $relation->getRelated()->getConnectionName();
    }

    /**
     * @return string
     */
    protected function getHasCompareKey(Relation $relation)
    {
        if (method_exists($relation, 'getHasCompareKey')) {
            return $relation->getHasCompareKey();
        }

        return $relation instanceof HasOneOrMany ? $relation->getForeignKeyName() : $relation->getOwnerKeyName();
    }

    /**
     * @return array
     */
    protected function getConstrainedRelatedIds($relations, $operator, $count)
    {
        $relationCount = array_count_values(array_map(function ($id) {
            return (string) $id; // Convert Back ObjectIds to Strings
        }, is_array($relations) ? $relations : $relations->flatten()->toArray()));
        // Remove unwanted related objects based on the operator and count.
        $relationCount = array_filter($relationCount, function ($counted) use ($count, $operator) {
            // If we are comparing to 0, we always need all results.
            if (0 == $count) {
                return true;
            }
            switch ($operator) {
                case '>=':
                case '<':
                    return $counted >= $count;
                case '>':
                case '<=':
                    return $counted > $count;
                case '=':
                case '!=':
                    return $counted == $count;
            }
        });

        // All related ids.
        return array_keys($relationCount);
    }

    /**
     * Returns key we are constraining this parent model's query with.
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getRelatedConstraintKey(Relation $relation)
    {
        if ($relation instanceof HasOneOrMany) {
            return $relation->getLocalKeyName();
        }

        if ($relation instanceof BelongsTo) {
            return $relation->getForeignKeyName();
        }

        if ($relation instanceof BelongsToMany && !$this->isAcrossConnections($relation)) {
            return $this->model->getKeyName();
        }

        throw new \Exception(class_basename($relation) . ' is not supported for hybrid query constraints.');
    }
}
