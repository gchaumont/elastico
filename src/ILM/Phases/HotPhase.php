<?php

namespace Elastico\ILM\Phases;

use Elastico\ILM\Actions\ShrinkAction;
use Elastico\ILM\Actions\ReadOnlyAction;
use Elastico\ILM\Actions\RolloverAction;
use Elastico\ILM\Actions\UnfollowAction;
use Elastico\ILM\Actions\DownsampleAction;
use Elastico\ILM\Actions\ForceMergeAction;
use Elastico\ILM\Actions\SetPriorityAction;
use Elastico\ILM\Actions\SearchableSnapshotAction;


/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class HotPhase extends Phase
{
    public function __construct(
        public string $min_age,
        public ?SetPriorityAction $set_priority = null,
        public ?UnfollowAction $unfollow = null,
        public ?RolloverAction $rollover = null,
        public ?ReadOnlyAction $readOnly = null,
        public ?DownsampleAction $downsample = null,
        public ?ShrinkAction $shrink = null,
        public ?ForceMergeAction $force_merge = null,
        public ?SearchableSnapshotAction $searchable_snapshot = null,
    ) {
    }
}
