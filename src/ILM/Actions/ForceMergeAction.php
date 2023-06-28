<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-forcemerge.html
 */
class ForceMergeAction extends Action
{
    public function __construct(
        public int $max_num_segments,
        public string $index_codec,
    ) {
    }
}
