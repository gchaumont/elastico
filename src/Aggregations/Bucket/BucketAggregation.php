<?php

namespace Gchaumont\Aggregations\Bucket;

use Gchaumont\Aggregations\Aggregation;
use Gchaumont\Query\Response\Aggregation\BucketResponse;

/**
 * Abstract Bucket Aggregation.
 */
abstract class BucketAggregation extends Aggregation
{
    const RESPONSE_CLASS = BucketResponse::class;
}
