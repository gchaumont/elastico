<?php

namespace Elastico\Eloquent;

use Closure;
use Elastico\Query\Builder\HasAggregations;
use Illuminate\Support\Traits\ForwardsCalls;
use Elastico\Eloquent\Concerns\LoadsAggregates;
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
