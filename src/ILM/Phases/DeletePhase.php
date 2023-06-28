<?php

namespace Elastico\ILM\Phases;

use Elastico\ILM\Actions\DeleteAction;
use Elastico\ILM\Actions\WaitForSnapshotAction;


/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class DeletePhase extends Phase
{
    public function __construct(
        public string $min_age,
        public ?WaitForSnapshotAction $wait_for_snapshot = null,
        public ?DeleteAction $delete = null,
    ) {
    }
}
