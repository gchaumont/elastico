<?php

namespace Elastico\Relations;

use Elastico\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class EmbedsOneOrMany extends Relation implements ElasticRelation
{
    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new embeds many relationship instance.
     *
     * @param string $localKey
     * @param string $foreignKey
     * @param string $relation
     */
    public function __construct(Builder $query, Model $parent, Model $related, $localKey, $foreignKey, $relation)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

        // If this is a nested relation, we need to get the parent query instead.
        if ($parentRelation = $this->getParentRelation()) {
            $this->query = $parentRelation->getQuery();
        }

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->getQualifiedParentKeyName(), '=', $this->getParentKey());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models)
    {
        // There are no eager loading constraints.
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results, $relation)
    {
        foreach ($models as $model) {
            $results = $model->{$relation}()->getResults();

            $model->setParentRelation($this);

            $model->setRelation($relation, $results);
        }

        return $models;
    }

    /**
     * Shorthand to get the results of the relationship.
     *
     * @param array $columns
     *
     * @return Collection
     */
    public function get($columns = ['*'])
    {
        return $this->getResults();
    }

    /**
     * Get the number of embedded models.
     *
     * @return int
     */
    public function count()
    {
        return count($this->getEmbedded());
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @return bool|Model
     */
    public function save(Model $model)
    {
        $model->setParentRelation($this);

        return $model->save() ? $model : false;
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param array|Collection $models
     *
     * @return array|Collection
     */
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @return Model
     */
    public function create(array $attributes = [])
    {
        // Here we will set the raw attributes to avoid hitting the "fill" method so
        // that we do not have to worry about a mass accessor rules blocking sets
        // on the models. Otherwise, some of these attributes will not get set.
        $instance = $this->related->newInstance($attributes);

        $instance->setParentRelation($this);

        $instance->save();

        return $instance;
    }

    /**
     * Create an array of new instances of the related model.
     *
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = [];

        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }

        return $instances;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        return clone $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function toBase()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        return clone $this->query->getQuery();
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedParentKeyName()
    {
        if ($parentRelation = $this->getParentRelation()) {
            return $parentRelation->getPathHierarchy() . '.' . $this->parent->getKeyName();
        }

        return $this->parent->getKeyName();
    }

    /**
     * Return update values.
     *
     * @param string $prepend
     * @param mixed  $array
     *
     * @return array
     */
    public static function getUpdateValues($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            $results[$prepend . $key] = $value;
        }

        return $results;
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Transform single ID, single Model or array of Models into an array of IDs.
     *
     * @param mixed $ids
     *
     * @return array
     */
    protected function getIdsArrayFrom($ids)
    {
        if ($ids instanceof \Illuminate\Support\Collection) {
            $ids = $ids->all();
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as &$id) {
            if ($id instanceof Model) {
                $id = $id->getKey();
            }
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEmbedded()
    {
        // Get raw attributes to skip relations and accessors.
        $attributes = $this->parent->getAttributes();

        // Get embedded models form parent attributes.
        return isset($attributes[$this->localKey]) ? (array) $attributes[$this->localKey] : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function setEmbedded($records)
    {
        // Assign models to parent attributes array.
        $attributes = $this->parent->getAttributes();
        $attributes[$this->localKey] = $records;

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        return $this->parent->setRelation($this->relation, null === $records ? null : $this->getResults());
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param mixed $id
     *
     * @return mixed
     */
    protected function getForeignKeyValue($id)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        // Convert the id to MongoId if necessary.
        return $this->getBaseQuery()->convertKey($id);
    }

    /**
     * Convert an array of records to a Collection.
     *
     * @return Collection
     */
    protected function toCollection(array $records = [])
    {
        $models = [];

        foreach ($records as $attributes) {
            $models[] = $this->toModel($attributes);
        }

        if (count($models) > 0) {
            $models = $this->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Create a related model instanced.
     *
     * @param array $attributes
     *
     * @return Model
     */
    protected function toModel($attributes = [])
    {
        if (null === $attributes) {
            return;
        }

        $connection = $this->related->getConnection();

        $model = $this->related->newFromBuilder(
            ['_source' => (array) $attributes],
            $connection ? $connection->getName() : null
        );

        $model->setParentRelation($this);

        $model->setRelation($this->foreignKey, $this->parent);

        // If you remove this, you will get segmentation faults!
        $model->setHidden(array_merge($model->getHidden(), [$this->foreignKey]));

        return $model;
    }

    /**
     * Get the relation instance of the parent.
     *
     * @return Relation
     */
    protected function getParentRelation()
    {
        return $this->parent->getParentRelation();
    }

    /**
     * Check if this relation is nested in another relation.
     *
     * @return bool
     */
    protected function isNested()
    {
        return null != $this->getParentRelation();
    }

    /**
     * Get the fully qualified local key name.
     *
     * @param string $glue
     *
     * @return string
     */
    protected function getPathHierarchy($glue = '.')
    {
        if ($parentRelation = $this->getParentRelation()) {
            return $parentRelation->getPathHierarchy($glue) . $glue . $this->localKey;
        }

        return $this->localKey;
    }

    /**
     * Get the primary key value of the parent.
     *
     * @return string
     */
    protected function getParentKey()
    {
        return $this->parent->getKey();
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
