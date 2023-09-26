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
    public function initialiseTimestamps(): void
    {
        $this->casts['created_at'] = 'datetime';
        $this->casts['updated_at'] = 'datetime';
    }

    public static function configTimestamps(Config $config): void
    {
        $config->properties(
            Field::make(name: 'created_at', type: 'date'),
            Field::make(name: 'updated_at', type: 'date')
        );
    }
}
