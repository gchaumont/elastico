<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Index\Config;
use stdClass;
use Elastico\Mapping\Field;

trait HasIndexConfig
{

    static array $indexConfigurators = [];

    abstract protected static function indexConfig(): Config;

    public static function getIndexConfig(): Config
    {
        static $cache;

        if (isset($cache[static::class])) {
            return $cache[static::class];
        }

        $config = static::indexConfig();

        $class = static::class;
        $booted = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'config' . class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, $booted)) {
                forward_static_call([$class, $method], $config);

                $booted[] = $method;
            }
        }

        foreach (static::$indexConfigurators[$class] ?? [] as $callback) {
            $callback($config);
        }

        return $cache[static::class] = $config;
    }

    public function configureIndexUsing(callable $callback): void
    {
        static::$indexConfigurators[static::class][] = $callback;
    }

    // {
    //     return 
    //     return [
    //         'index' => (new static())->getTable(),
    //         'body' => [
    //             'settings' => static::indexSettings(),
    //             'mappings' => array_filter([
    //                 '_source' => static::getSourceSettings(),
    //                 'dynamic' => static::getDynamicMapping(),
    //                 'properties' => collect(static::indexProperties())
    //                     ->keyBy(fn ($prop) => $prop->getName())
    //                     ->map(fn ($prop) => $prop->toArray())
    //                     ->all(),
    //                 // 'dynamic_templates' => static::getDynamicTemplates(),
    //             ]),
    //         ],
    //     ];
    // }

    public static function getFieldNames(): array
    {
        return collect(static::getIndexConfig()->mappings->properties)
            ->map(fn (Field $field) => $field->getName())
            ->all();
    }

    // public static function indexSettings()
    // {
    //     return static::$index_settings ??  new stdClass();
    // }

    // public static function getDynamicMapping(): string
    // {
    //     return 'strict';
    // }

    // public static function getSourceSettings(): array
    // {
    //     return [];
    // }
}
