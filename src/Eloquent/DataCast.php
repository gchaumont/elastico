<?php

namespace Elastico\Eloquent;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Exceptions\CannotCastData;
use Spatie\LaravelData\Contracts\TransformableData;
use Spatie\LaravelData\Support\DataConfig;


class DataCast implements CastsAttributes //extends DataEloquentCast
{

    protected DataConfig $dataConfig;

    public function __construct(
        /** @var class-string<BaseData> $dataClass */
        protected string $dataClass,
        /** @var string[] $arguments */
        protected array $arguments = []
    ) {
        $this->dataConfig = app(DataConfig::class);
    }

    public function get($model, string $key, $value, array $attributes): ?BaseData
    {
        if (is_null($value) && in_array('default', $this->arguments)) {
            $value = '{}';
        }

        if ($value === null) {
            return null;
        }

        $payload = $value;

        if ($this->isAbstractClassCast()) {
            /** @var class-string<BaseData> $dataClass */
            $dataClass = $this->dataConfig->morphMap->getMorphedDataClass($payload['type']) ?? $payload['type'];

            return $dataClass::from($payload['data']);
        }

        return ($this->dataClass)::from($payload);
    }

    public function set($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $isAbstractClassCast = $this->isAbstractClassCast();

        if (is_array($value) && !$isAbstractClassCast) {
            $value = ($this->dataClass)::from($value);
        }

        if (!$value instanceof BaseData) {
            throw CannotCastData::shouldBeData($model::class, $key);
        }

        if (!$value instanceof TransformableData) {
            throw CannotCastData::shouldBeTransformableData($model::class, $key);
        }

        if ($isAbstractClassCast) {
            return [$key => [
                'type' => $this->dataConfig->morphMap->getDataClassAlias($value::class) ?? $value::class,
                'data' => json_decode($value->toJson(), associative: true, flags: JSON_THROW_ON_ERROR),
            ]];
        }

        return [
            $key => $value->toArray()
        ];
    }

    protected function isAbstractClassCast(): bool
    {
        return $this->dataConfig->getDataClass($this->dataClass)->isAbstract;
    }
}
