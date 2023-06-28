<?php

namespace Elastico\ILM\Phases;

use Elastico\ILM\Actions\MigrateAction;
use Elastico\ILM\Actions\AllocateAction;
use Elastico\ILM\Actions\ReadOnlyAction;
use Elastico\ILM\Actions\UnfollowAction;
use Elastico\ILM\Actions\DownsampleAction;
use Elastico\ILM\Actions\SetPriorityAction;
use Elastico\ILM\Actions\SearchableSnapshotAction;


/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class ColdPhase extends Phase
{
    public function __construct(
        public string $min_age,
        public ?SetPriorityAction $set_priority = null,
        public ?UnfollowAction $unfollow = null,
        public ?ReadOnlyAction $readOnly = null,
        public ?DownsampleAction $downsample = null,
        public ?SearchableSnapshotAction $searchable_snapshot = null,
        public ?AllocateAction $allocate = null,
        public ?MigrateAction $migrate = null,
    ) {
    }
}
