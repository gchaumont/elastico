<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-wait-for-snapshot.html
 */
class WaitForSnapshotAction extends Action
{
    public function __construct(
        public string $policy,
    ) {
    }
}
