<?php

namespace Elastico\ILM\Phases;

use Elastico\ILM\Actions\UnfollowAction;
use Elastico\ILM\Actions\SearchableSnapshotAction;


/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class FrozenPhase extends Phase
{
    public function __construct(
        public string $min_age,
        public ?UnfollowAction $unfollow = null,
        public ?SearchableSnapshotAction $searchable_snapshot = null
    ) {
    }
}
