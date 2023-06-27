<?php

namespace Elastico\Eloquent\Concerns;

use stdClass;
use Elastico\Mapping\Field;

trait IndexConfiguration
{
    public static array $index_settings;

    public static function getIndexConfiguration(): array
    {
        return [
            'index' => (new static())->getTable(),
            'body' => [
                'settings' => static::indexSettings(),
                'mappings' => array_filter([
                    '_source' => static::getSourceSettings(),
                    'dynamic' => static::getDynamicMapping(),
                    'properties' => collect(static::indexProperties())
                        ->keyBy(fn ($prop) => $prop->getName())
                        ->map(fn ($prop) => $prop->toArray())
                        ->all(),
                    // 'dynamic_templates' => static::getDynamicTemplates(),
                ]),
            ],
        ];
    }

    public static function getFieldNames(): array
    {
        return collect(static::indexProperties())
            ->map(fn (Field $field) => $field->getName())
            ->all();
    }

    public static function indexSettings()
    {
        return static::$index_settings ??  new stdClass();
    }

    public static function getDynamicMapping(): string
    {
        return 'strict';
    }

    public static function getSourceSettings(): array
    {
        return [];
    }

    // public static function getTemplateConfiguration(): array
    // {
    //     $configuration = static::getIndexConfiguration();

    //     $configuration['body'] = [
    //         'index_patterns' => $configuration['index'],
    //         'template' => $configuration['body'],
    //     ];

    //     return $configuration;
    // }
}
