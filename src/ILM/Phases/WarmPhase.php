<?php

namespace Elastico\ILM\Phases;

use Elastico\ILM\Actions\ShrinkAction;
use Elastico\ILM\Actions\MigrateAction;
use Elastico\ILM\Actions\AllocateAction;
use Elastico\ILM\Actions\ReadOnlyAction;
use Elastico\ILM\Actions\UnfollowAction;
use Elastico\ILM\Actions\DownsampleAction;
use Elastico\ILM\Actions\ForceMergeAction;
use Elastico\ILM\Actions\SetPriorityAction;


/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class WarmPhase extends Phase
{
    public function __construct(
        public string $min_age,
        public ?SetPriorityAction $set_priority = null,
        public ?UnfollowAction $unfollow = null,

        public ?ReadOnlyAction $read_only = null,
        public ?DownsampleAction $downsample = null,
        public ?AllocateAction $allocate = null,
        public ?MigrateAction $migrate = null,
        public ?ShrinkAction $shrink = null,
        public ?ForceMergeAction $force_merge = null,
    ) {
    }
}
