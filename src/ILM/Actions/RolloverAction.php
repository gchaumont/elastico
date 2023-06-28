<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-rollover.html
 */
class RolloverAction extends Action
{
    public function __construct(
        public string $max_age,
        public int $max_docs,
        public string $max_size,
        public string $max_primary_shard_size,
        public int $max_primary_shard_docs,
        public string $min_age,
        public int $min_docs,
        public string $min_size,
        public string $min_primary_shard_size,
        public int $min_primary_shard_docs,

    ) {
    }
}
