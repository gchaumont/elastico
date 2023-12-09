<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Index\Config;
use stdClass;
use Elastico\Mapping\Field;

trait HasIndexConfig
{
    protected static array $indexConfigurators = [];

    abstract protected static function indexConfig(): Config;

    public static function getIndexConfig(): Config
    {
        static $cache;

        if (isset($cache[static::class])) {
            return $cache[static::class];
        }

        $config = static::indexConfig();

        foreach (static::$indexConfigurators[static::class] ?? [] as $callback) {
            $callback($config);
        }

        return $cache[static::class] = $config;
    }

    public static function configureIndexUsing(callable $callback): void
    {
        static::$indexConfigurators[static::class][] = $callback;
    }

    public static function getFieldNames(): array
    {
        return collect(static::getIndexConfig()->mappings?->properties)
            ->map(fn (Field $field) => $field->getName())
            ->all();
    }
}
