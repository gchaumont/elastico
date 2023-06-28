<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-shrink.html
 */
class ShrinkAction extends Action
{
    public function __construct(
        public int $number_of_shards,
        public string $max_primary_shard_size,
    ) {
    }
}
