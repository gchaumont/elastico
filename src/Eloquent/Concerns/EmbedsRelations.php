<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Relations\EmbedsMany;
use Elastico\Relations\EmbedsOne;
use Illuminate\Support\Str;

trait EmbedsRelations
{
    /**
     * Define an embedded one-to-many relationship.
     *
     * @param string $related
     * @param string $localKey
     * @param string $foreignKey
     * @param string $relation
     *
     * @return \Jenssegers\Mongodb\Relations\EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (null === $relation) {
            [, $caller] = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (null === $localKey) {
            $localKey = $relation;
        }

        if (null === $foreignKey) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related();

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param string $related
     * @param string $localKey
     * @param string $foreignKey
     * @param string $relation
     *
     * @return \Jenssegers\Mongodb\Relations\EmbedsOne
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (null === $relation) {
            [, $caller] = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (null === $localKey) {
            $localKey = $relation;
        }

        if (null === $foreignKey) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related();

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }
}
