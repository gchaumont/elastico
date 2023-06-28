<?php

namespace Elastico\ILM\Actions;

use stdClass;

/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-allocate.html
 */
class AllocateAction extends Action
{
    public function __construct(
        public int $number_of_replicas,
        public int $total_shards_per_node,
        public stdClass $include,
        public stdClass $exclude,
        public stdClass $require,

    ) {
    }
}
