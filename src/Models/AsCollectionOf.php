<?php

namespace Elastico\Models;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Support\Collection;

class AsCollectionOf extends AsCollection
{
    public static function castUsing(array $arguments)
    {
        $data_class = $arguments[0];

        return new class($data_class) implements CastsAttributes
        {
            public function __construct(public string $data_class)
            {
                // $this->data_class = $data_class;
            }

            public function get($model, $key, $value, $attributes)
            {
                if (!isset($attributes[$key])) {
                    return;
                }


                if ($value === null) {
                    return null;
                }


                $data = $value;  // $attributes[$key];
                $class = $this->data_class;

                $isEnum = enum_exists($class);

                if (!is_array($data)) {
                    return null;
                }

                return Collection::make($data)
                    ->when($isEnum, fn ($collection) => $collection->map(fn ($item) => $class::tryFrom($item)))
                    ->when(!$isEnum, fn ($collection) => $collection->map(fn ($item) => new $class($item)));
            }

            public function set($model, $key, $value, $attributes)
            {
                if ($value === null) {
                    return null;
                }

                if ($value instanceof Collection) {
                    $value = $value->all();
                }

                if (!is_array($value)) {
                    throw new \Exception("Cannot cast data");
                }

                return [$key => collect($value)->toArray()];
            }
        };
    }
}
