<?php

namespace Gchaumont\Aggregations\Bucket;

use Gchaumont\Aggregations\Aggregation;
use Gchaumont\Query\Query;

/**
 * Filter Aggregation.
 */
class Filter extends BucketAggregation
{
    public string $type = 'filter';

    public array|Query $filter;

    public int $min_doc_count;

    public int $size = 10;

    public function getPayload(): array
    {
        if (is_array($this->filter)) {
            return $this->filter;
        }

        return $this->filter->compile();
    }

    public function filter(array|Query $filter): self
    {
        $this->filter = $filter;

        return $this;
    }
}
