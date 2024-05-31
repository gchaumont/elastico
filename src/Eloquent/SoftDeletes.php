<?php

namespace Elastico\Eloquent;

use Elastico\Index\Config;
use Elastico\Mapping\Field;
use Carbon\Carbon;

/**
 * @property Carbon|null $deleted_at
 * @mixin Model
 */
trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function initializeSoftDeletes(): void
    {
        $this->mergeCasts([
            $this->getDeletedAtColumn() => 'datetime',
        ]);

        $this->configureIndexUsing(fn (Config $config) => $config->properties(
            Field::make(name: $this->getDeletedAtColumn(), type: 'date')
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
}
