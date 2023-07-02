<?php

namespace Elastico\Eloquent;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Elastico\Query\Response\Response;
use Elastico\Eloquent\Concerns\HasAggregations;
use Elastico\Eloquent\Concerns\HybridRelations;
use Elastico\Eloquent\Concerns\IndexConfiguration;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Model.
 * @method static \Elastico\Eloquent\Builder whereIn()
 */
class Model extends BaseModel implements Castable
{
    use IndexConfiguration;
    use HybridRelations;
    use HasAggregations;
    // use EmbedsRelations;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'c';

    protected $connection = 'elastic';


    public $_score = null;

    // public $_seq_no = null;

    // public $_primary_term = null;

    // public $_version = null;

    // public $_explanation = null;

    /**
     * The parent relation instance.
     *
     * @var Relation
     */
    protected $parentRelation;

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new Response($models);
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param array       $attributes
     * @param null|string $connection
     *
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $hit = $attributes;

        $attributes = $hit['_source'];
        $attributes['_id'] = $hit['_id'];
        $attributes['_index'] = $hit['_index'];

        $new = parent::newFromBuilder($attributes, $connection);

        $new->_score = $hit['_score'] ?? null;

        return $new;
    }

    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Qualify the given column name by the model's table.
     *
     * @param string $column
     *
     * @return string
     */
    public function qualifyColumn($column)
    {
        return $column;
    }

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @return string
     */
    public static function castUsing(array $arguments)
    {
        $class = static::class;

        return new class($class) implements CastsAttributes
        {
            public function __construct(protected string $class)
            {
            }

            public function get($model, $key, $value, $attributes)
            {
                $class = $this->class;

                if (is_subclass_of($class, Model::class)) {
                    return (new $class())->forceFill($value);
                }

                return new $class($value);
            }

            public function set($model, $key, $value, $attributes)
            {
                return [$key => $value->getAttributes()];
            }
        };
    }

    /**
     * Set the parent relation.
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getParentRelation()
    {
        return $this->parentRelation;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }

        // Dot notation support.
        if (Str::contains($key, '.') && Arr::has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        // This checks for embedded relation support.
        if (method_exists($this, $key) && !method_exists(self::class, $key)) {
            return $this->getRelationValue($key);
        }

        return parent::getAttribute($key);
    }

    protected function getStorableEnumValue($value)
    {
        if ($value === null) {
            return null;
        }

        return parent::getStorableEnumValue($value);
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation $query
     * @param mixed                                                                                $value
     * @param null|string                                                                          $field
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field == $this->getKeyName()) {
            return collect([static::find($value)]);
        }

        return $query->where($field, $value);
    }

    protected function getAttributesForInsert()
    {
        $attributes = $this->getAttributes();
        $attributes['_id'] = $this->getKey();
        $attributes['_index'] = $this->getTable();

        return $attributes;
    }

    /**
     * Get an enum case instance from a given class and value.
     *
     * @param string     $enumClass
     * @param int|string $value
     *
     * @return \BackedEnum|\UnitEnum
     */
    protected function getEnumCaseFromValue($enumClass, $value)
    {
        return is_subclass_of($enumClass, \BackedEnum::class)
            ? $enumClass::tryFrom($value)
            : constant($enumClass . '::' . $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (Str::contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }

        return parent::getAttributeFromArray($key);
    }

    protected function setKeysForSaveQuery($query)
    {
        $query->getQuery()->model_id = $this->getKeyForSaveQuery();
        // $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Begin querying the model.
     *
     * @return \Elastico\Eloquent\Builder
     */
    public static function query()
    {
        return (new static)->newQuery();
    }

    /**
     * Perform a model update operation.
     *
     * @return bool
     */
    protected function performUpdate(EloquentBuilder $query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if (false === $this->fireModelEvent('updating')) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $dirty = array_merge(
                [
                    '_id' => $this->getKey(),
                    '_index' => $this->getTable(),
                ],
                $dirty,
            );

            $query->update($dirty);

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }


    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel()
    {
        $this->newModelQuery()->delete($this->getKey());
        // $this->setKeysForSaveQuery($this->newModelQuery())->delete();

        $this->exists = false;
    }
}
