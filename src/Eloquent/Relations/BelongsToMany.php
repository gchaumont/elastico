<?php

namespace Elastico\Eloquent\Relations;

use Elastico\Query\Query;
use Illuminate\Support\Arr;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Terms;
use Elastico\Query\Compound\Boolean;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Elastico\Eloquent\Relations\Concerns\MatchesAttributes;
use Elastico\Query\MatchNone;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;

class BelongsToMany extends EloquentBelongsToMany implements ElasticRelation
{
    use MatchesAttributes;
    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->whereRaw($this->buildConstraint($this->parent));
            // $this->setWhere();
        }
    }

    public function buildConstraint(EloquentModel $parent): Query
    {
        $keys = Arr::get($parent, $this->parentKey);

        if (empty($keys)) {
            return new MatchNone;
        }

        $query = new Terms(field: $this->getQualifiedForeignPivotKeyName(), values: $keys);

        if ($this->hasAttributeMatches()) {
            $query = $this->getAttributeMatchesQuery($parent)->filter($query);
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
            $hasConstraints = false;

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


        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);

        $this->take(collect($models)->pluck($this->parentKey)->flatten()->unique()->count());
        $this->whereInEager(
            $whereIn,
            $this->getQualifiedForeignPivotKeyName(),
            $this->getKeys($models, $this->parentKey)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function save(Model $model, array $joining = [], $touch = true)
    {
        $model->save(['touch' => false]);

        $this->attach($model, $joining, $touch);

        return $model;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $instance = $this->related->newInstance($attributes);

        // Once we save the related model, we need to attach it to the base model via
        // through intermediate table so we'll use the existing "attach" method to
        // accomplish this which will insert the record and any more attributes.
        $instance->save(['touch' => false]);

        $this->attach($instance, $joining, $touch);

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function sync($ids, $detaching = true)
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        if ($ids instanceof Collection) {
            $ids = $ids->modelKeys();
        }

        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->parent->{$this->getRelatedKey()} ?: [];

        // See issue #256.
        if ($current instanceof Collection) {
            $current = $ids->modelKeys();
        }

        $records = $this->formatSyncList($ids);

        $current = Arr::wrap($current);

        $detach = array_diff($current, array_keys($records));

        // We need to make sure we pass a clean array, so that it is not interpreted
        // as an associative array.
        $detach = array_values($detach);

        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // the array of the IDs given to the method which will complete the sync.
        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = (array) array_map(function ($v) {
                return is_numeric($v) ? (int) $v : (string) $v;
            }, $detach);
        }

        // Now we are finally ready to attach the new records. Note that we'll disable
        // touching until after the entire operation is complete so we don't fire a
        // ton of touch operations until we are totally done syncing the records.
        $changes = array_merge(
            $changes,
            $this->attachNew($records, $current, false)
        );

        if (count($changes['attached']) || count($changes['updated'])) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * {@inheritdoc}
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        // Do nothing, we have no pivot table.
    }

    /**
     * {@inheritdoc}
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        if ($id instanceof Model) {
            $model = $id;

            $id = $model->getKey();

            // Attach the new parent id to the related model.
            $model->push($this->foreignPivotKey, $this->parent->getKey(), true);
        } else {
            if ($id instanceof Collection) {
                $id = $id->modelKeys();
            }

            $query = $this->newRelatedQuery();

            $query->whereIn($this->related->getKeyName(), (array) $id);

            // Attach the new parent id to the related model.
            $query->push($this->foreignPivotKey, $this->parent->getKey(), true);
        }

        // Attach the new ids to the parent model.
        $this->parent->push($this->getRelatedKey(), (array) $id, true);

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach($ids = [], $touch = true)
    {
        if ($ids instanceof Model) {
            $ids = (array) $ids->getKey();
        }

        $query = $this->newRelatedQuery();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        $ids = (array) $ids;

        // Detach all ids from the parent model.
        $this->parent->pull($this->getRelatedKey(), $ids);

        // Prepare the query to select all related objects.
        if (count($ids) > 0) {
            $query->whereIn($this->related->getKeyName(), $ids);
        }

        // Remove the relation to the parent.
        $query->pull($this->foreignPivotKey, $this->parent->getKey());

        if ($touch) {
            $this->touchIfTouching();
        }

        return count($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function newPivotQuery()
    {
        return $this->newRelatedQuery();
    }

    /**
     * Create a new query builder for the related model.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function newRelatedQuery()
    {
        return $this->related->newQuery();
    }

    /**
     * Get the fully qualified foreign key for the relation.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignPivotKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedForeignPivotKeyName()
    {
        return $this->foreignPivotKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedRelatedPivotKeyName()
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the related key with backwards compatible support.
     *
     * @return string
     */
    public function getRelatedKey()
    {
        return property_exists($this, 'relatedPivotKey') ? $this->relatedPivotKey : $this->relatedKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function hydratePivotRelation(array $models)
    {
        // Do nothing.
    }

    /**
     * Set the select clause for the relation query.
     *
     * @return array
     */
    protected function getSelectColumns(array $columns = ['*'])
    {
        return $columns;
    }

    /**
     * {@inheritdoc}
     */
    protected function shouldSelect(array $columns = ['*'])
    {
        return $columns;
    }

    // /**
    //  * Set the where clause for the relation query.
    //  *
    //  * @return $this
    //  */
    // protected function setWhere()
    // {

    //     $foreign = $this->getForeignKey();

    //     $this->query->where($foreign, '=', $this->parent->getKey());

    //     return $this;
    // }

    /**
     * {@inheritdoc}
     */
    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->foreignPivotKey;

        // First we will build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to their
        // parents without having a possibly slow inner loops for every models.
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($foreign)][] = $result;
            // foreach ($result->{$foreign} as $item) {
            // }
        }

        return $dictionary;
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

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // parent models. Then we should return these hydrated models back out.
        foreach ($models as $model) {
            $relationCollection = $this->related->newCollection();

            foreach ($model->{$this->parentKey} as $key) {
                $key = $this->getDictionaryKey($key);

                if (isset($dictionary[$key])) {
                    $relationCollection->push(...$dictionary[$key]);
                }
            }


            if ($this->hasAttributeMatches()) {
                $relationCollection = $relationCollection->filter(function ($item) use ($model) {
                    return $this->matchesAttributes(model: $model, related: $item);
                });
            }

            $model->setRelation($relation, $relationCollection);
        }

        return $models;
    }

    /**
     * Format the sync list so that it is keyed by ID. (Legacy Support)
     * The original function has been renamed to formatRecordsList since Laravel 5.3.
     *
     * @return array
     *
     * @deprecated
     */
    protected function formatSyncList(array $records)
    {
        $results = [];
        foreach ($records as $id => $attributes) {
            if (!is_array($attributes)) {
                [$id, $attributes] = [$attributes, []];
            }
            $results[$id] = $attributes;
        }

        return $results;
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


    protected function getKeys(array $models, $key = null)
    {
        return collect($models)->map(function ($value) use ($key) {
            return $key ? Arr::get($value, $key) : $value->getKey();
            // return $key ? $value->getAttribute($key) : $value->getKey();
        })
            ->collapse()
            ->values()
            ->unique(null, true)
            ->filter()
            ->sort()
            ->values()
            ->all();
    }
}
