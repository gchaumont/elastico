<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-migrate.html
 */
class MigrateAction extends Action
{
    public function __construct(public bool $enabled)
    {
    }
}
