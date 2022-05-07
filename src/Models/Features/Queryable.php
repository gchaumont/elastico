<?php

namespace Elastico\Models\Features;

use Elastico\Models\Builder\Builder;

trait Queryable
{
    public static function query(): Builder
    {
        return new Builder(
            connection: static::getConnection(),
            model: static::class,
        );
    }

    public function fresh()
    {
        return static::query()->find($this->get_id());
    }

    public function refresh()
    {
        // rehydrate $this model
    }
}
