<?php

namespace Elastico\ILM\Actions;

/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-set-priority.html
 */
class SetPriorityAction extends Action
{
    public function __construct(public int $priority)
    {
    }
}
