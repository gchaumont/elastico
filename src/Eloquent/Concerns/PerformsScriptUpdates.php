<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Eloquent\Builder;
use Elastico\Scripting\Script;
use Elastico\Query\Response\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Stores aggregation results on the model.
 */
trait PerformsScriptUpdates
{
    /**
     * Perform a model update operation.
     * @param \Elastico\Eloquent\Builder $query
     * 
     * @return bool
     */
    protected function performScriptedUpdate(Builder $query,  Script $script)
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
        // $dirty = $this->getDirty();

        // if (count($dirty) > 0) {

        $query->update($script);

        $this->syncChanges();

        $this->fireModelEvent('updated', false);
        // }

        return true;
    }
}
