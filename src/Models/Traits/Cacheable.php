<?php

namespace Elastico\Models\Traits;

use Elastico\Models\Model;
use Illuminate\Support\Facades\Cache;

trait Cacheable
{
    public static function find(string $id): static
    {
        return Cache::remember(static::getCacheKey($this), now()->addMinutes(10), function ($id) {
            return parent::find($id);
        });
    }

    public function findMany(array $ids): array
    {
        $items = Cache::many(array_map(fn ($id) => static::getCacheKey($id)));

        $newItems = parent::findMany(array_filter($items, fn ($item) => null == $item));

        Cache::setMultiple($newItems, now()->addMinutes(10));

        return array_merge($items, $newItems);
    }

    private static function getCacheKey(Model $that): string
    {
        return static::class.'.'.$that->getKey();
    }
}
