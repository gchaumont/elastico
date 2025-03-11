<?php

namespace Elastico\Eloquent;

use Elastico\Eloquent\Concerns\ElasticModel;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Database\Eloquent\Model as BaseModel;

/**
 * Model.
 * @method static \Elastico\Eloquent\Builder whereIn()
 */
abstract class Model extends BaseModel implements Castable
{
    use ElasticModel;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'c';

    protected $connection = 'elastic';
}
