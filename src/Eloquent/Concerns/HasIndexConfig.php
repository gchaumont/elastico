<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Index\Config;
use stdClass;
use Elastico\Mapping\Field;

trait HasIndexConfig
{
    use HasIndexProperties;

    protected static array $indexConfigurators = [];

    abstract protected static function indexConfig(): Config;

    public function initializeHasIndexConfig(): void
    {
        collect(static::getIndexProperties())
            ->filter(static fn (Field $field) => !empty($field->object))
            ->tap(fn ($fields) => $this->mergeCasts($fields
                ->mapWithKeys(fn (Field $field) => [$field->getName() => $field->object])
                ->all()));

        collect(static::getIndexProperties())
            ->filter(static fn (Field $field) => !empty($field->cast))
            ->tap(fn ($fields) => $this->mergeCasts($fields
                ->mapWithKeys(fn (Field $field) => [$field->getName() => $field->cast])
                ->all()));
    }

    public static function getIndexConfig(): Config
    {
        static $cache;

        if (isset($cache[static::class])) {
            return $cache[static::class];
        }

        $config = static::indexConfig();

        $config->properties(...static::getIndexProperties());

        # run configurators 
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
