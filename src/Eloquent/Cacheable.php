<?php

namespace Elastico\Eloquent;

use Carbon\Carbon;
use Elastico\Index\Config;
use Elastico\Mapping\Field;
use Elastico\Eloquent\Model;
use Elastico\Eloquent\Builder;

/** 
 * @mixin Model
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
trait Cacheable
{

    public function scopeCached(Builder $builder): CachedBuilder
    {
        return new CachedBuilder($builder);
    }
}
