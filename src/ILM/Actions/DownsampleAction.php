<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-downsample.html
 */
class DownsampleAction extends Action
{
    public function __construct(
        public string $fixed_interval
    ) {
    }
}
