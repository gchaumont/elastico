<?php

namespace Elastico\Eloquent\Concerns;

trait IndexConfiguration
{
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

    public static function indexSettings()
    {
        return new \stdClass();
    }

    public static function getDynamicMapping(): string
    {
        return 'strict';
    }

    public static function getSourceSettings(): array
    {
        return [];
    }
}
