<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;
use Elastico\Query\Query;

/**
 * Filter Aggregation.
 */
class Filter extends BucketAggregation
{
    public const TYPE = 'filter';

    public function __construct(
        public array|Query $filter,
        public null|int $min_doc_count = null,
        public null|int $size = null,
    ) {
    }

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
