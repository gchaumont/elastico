<?php

namespace Elastico\ILM\Actions;


/**
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/ilm-searchable-snapshot.html
 */
class SearchableSnapshotAction extends Action
{
    public function __construct(
        public string $snapshot_repository,
        public bool $force_merge_index,
    ) {
    }
}
