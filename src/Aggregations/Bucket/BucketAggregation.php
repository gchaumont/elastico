<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;
use Elastico\Query\Response\Aggregation\BucketResponse;

/**
 * Abstract Bucket Aggregation.
 */
abstract class BucketAggregation extends Aggregation
{
    const RESPONSE_CLASS = BucketResponse::class;
}
