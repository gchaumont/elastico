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
trait Timestamps
{
    public function initializeTimestamps(): void
    {
        $this->casts['created_at'] = 'datetime';
        $this->casts['updated_at'] = 'datetime';

        $this->configureIndexUsing(fn (Config $config) =>  $config->properties(
            Field::make(name: 'created_at', type: 'date'),
            Field::make(name: 'updated_at', type: 'date')
        ));
    }
}
