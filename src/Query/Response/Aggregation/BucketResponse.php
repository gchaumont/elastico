<?php

namespace Elastico\Query\Response\Aggregation;

use Elastico\Aggregations\Aggregation;
use Elastico\Query\Response\Response;
use Illuminate\Support\Collection;

/**
 *  Aggregation Response.
 */
class BucketResponse extends AggregationResponse implements \ArrayAccess
{
    // For Bucket Aggregations
    public function buckets(): Collection
    {
        return $this->collect('buckets')
            ->transform(fn ($bucket) => $this->aggregation->toResponse(
                response: $bucket,
            ));
    }

    public function doc_count(): int
    {
        return $this->get('doc_count');
    }
}
