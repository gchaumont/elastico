<?php

namespace Gchaumont\Models\Features;

use Gchaumont\Query\Builder;

trait Queryable
{
    public static function query(): Builder
    {
        return new Builder(model: static::class);
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
