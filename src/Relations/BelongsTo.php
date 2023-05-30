<?php

namespace Elastico\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;

class BelongsTo extends EloquentBelongsTo implements ElasticRelation
{
    protected $findKeys;
    protected $noRelatedKeys;
    /**
     * Indicates whether the eagerly loaded relation should implicitly return an empty collection.
     *
     * @var bool
     * TODO: check if replace 
     */
    // protected $eagerKeysWereEmpty = false;

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getOwnerKey();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->getOwnerKey(), '=', $this->parent->{$this->foreignKey});
        }
    }

    public function get($columns = ['*'])
    {
        if ($this->findKeys) {
            return $this->query->findMany($this->findKeys);
        }
        if ($this->noRelatedKeys) {
            return new Collection();
        }

        return $this->query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->getOwnerKey();

        if ($key == $this->getModel()->getKeyName()) {
            // Resolve the relation with [elastic/find]
            $this->findKeys = $this->getEagerModelKeys($models);
        }
        $keys = array_filter($this->getEagerModelKeys($models));
        if (empty($keys)) {
            $this->noRelatedKeys = true;
        }

        $this->query->whereIn($key, $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query;
    }

    /**
     * Get the owner key with backwards compatible support.
     *
     * @return string
     */
    public function getOwnerKey()
    {
        return property_exists($this, 'ownerKey') ? $this->ownerKey : $this->otherKey;
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
}
