<?php

namespace Elastico\Eloquent;

use Elastico\Eloquent\Concerns\LoadsAggregates;
use Elastico\Query\Builder\HasAggregations;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 *  Elasticsearch Query Builder
 *  Extension of Larvel Database Eloquent Builder.
 */
class HybridEloquentBuilder extends EloquentBuilder
{
    use LoadsAggregates;
    use HasAggregations;
}
