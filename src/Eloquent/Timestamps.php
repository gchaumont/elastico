<?php

namespace Elastico\Eloquent;

use Carbon\Carbon;
use Elastico\Index\Config;
use Elastico\Mapping\Field;
use Elastico\Eloquent\Model;

/** 
 * @mixin Model
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Field(name: 'created_at', type: 'date', cast: 'datetime')]
#[Field(name: 'updated_at', type: 'date', cast: 'datetime')]
trait Timestamps
{
}
