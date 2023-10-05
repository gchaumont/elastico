<?php

namespace Elastico\Eloquent\Relations;

use Elastico\Query\Query;
use Illuminate\Support\Arr;
use Elastico\Eloquent\Model;
use Elastico\Query\MatchNone;
use Elastico\Query\Term\Term;
use Elastico\Query\Compound\Boolean;
use Elastico\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Elastico\Eloquent\Relations\Concerns\MatchesAttributes;
use Elastico\Eloquent\Relations\Concerns\BuildsDictionnaries;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;

class HasMany extends EloquentHasMany implements ElasticRelation
{
    use MatchesAttributes;
    use BuildsDictionnaries;

    public function one()
    {
        return HasOne::noConstraints(fn () => new HasOne(
            $this->getQuery(),
            $this->parent,
            $this->foreignKey,
            $this->localKey
        ));
    }


    /**
     * Get the plain foreign key.
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
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $query = $this->getRelationQuery();

            // $query->where($this->foreignKey, '=', $this->getParentKey());
            $query->whereRaw($this->buildConstraint($this->parent));
        }
    }

    public function buildConstraint(EloquentModel $model): Query
    {
        $key = Arr::get($model, $this->localKey);

        if (empty($key)) {
            return new MatchNone();
        }

        $query = new Term(field: $this->foreignKey, value: $key);

        if ($this->hasAttributeMatches()) {
            $query = $this->getAttributeMatchesQuery($model)->filter($query);
        }

        return $query;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if ($this->hasAttributeMatches()) {
            $hasConstraints = (bool) false;

            $this->where(function ($builder) use ($models, &$hasConstraints) {
                collect($models)->each(function ($model) use ($builder, &$hasConstraints) {
                    $constraints = $this->buildConstraint($model);
                    if ($constraints) {
                        $hasConstraints = true;
                        $builder->orWhereRaw($constraints);
                    }
                });
            });


            if (!$hasConstraints) {
                $this->eagerKeysWereEmpty = true;
            }
            return;
        }
        $whereIn = $this->whereInMethod($this->parent, $this->localKey);

        // TODO: use buildConstraint when necessary
        $this->whereInEager(
            $whereIn,
            $this->foreignKey,
            $this->getKeys($models, $this->localKey),
            $this->getRelationQuery()
        );
    }

    /**
     * Get all of the primary keys for an array of models.
     *
     * @param  array  $models
     * @param  string|null  $key
     * @return array
     */
    protected function getKeys(array $models, $key = null)
    {
        return collect($models)->map(function ($value) use ($key) {
            return $key ? Arr::get($value, $key) : $value->getKey();
        })->values()->unique(null, true)->sort()->all();
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getHasCompareKey();

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
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);


        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $this->getDictionaryKey($model->getAttribute($this->localKey))])) {
                $model->setRelation(
                    $relation,
                    $this->getRelationValue($dictionary, $key, 'many')
                        ->when($this->hasAttributeMatches(), function ($collection) use ($model) {
                            return $collection->filter(function ($item) use ($model) {
                                return $this->matchesAttributes(model: $model, related: $item);
                            });
                        })
                );
            }
        }

        return $models;
    }
}
