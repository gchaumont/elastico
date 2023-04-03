<?php

namespace Elastico\Models;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Support\Collection;

class AsCollectionOf extends AsCollection
{
    public static function castUsing(array $arguments)
    {
        $type = $arguments[0];

        return new class($type) implements CastsAttributes {
            public function __construct(public string $type)
            {
                // $this->type = $type;
            }

            public function get($model, $key, $value, $attributes)
            {
                if (!isset($attributes[$key])) {
                    return;
                }

                $data = $attributes[$key];
                $class = $this->type;

                return is_array($data) ? (new Collection($data))->map(fn ($item) => new $class($item)) : null;
            }

            public function set($model, $key, $value, $attributes)
            {
                return [$key => json_encode($value)];
            }
        };
    }
}
