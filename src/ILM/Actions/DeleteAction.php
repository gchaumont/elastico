<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-delete.html
 */
class DeleteAction extends Action
{
    public function __construct(
        public bool $delete_searchable_snapshot,
    ) {
    }
}
