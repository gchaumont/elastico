<?php

namespace Elastico\Models\Features;

trait Configurable
{
    public function writableIndexName(): string
    {
        return $this->_index ?? static::INDEX_NAME;
    }

    public static function searchableIndexName(): string|array
    {
        return static::INDEX_NAME;
    }

    public static function getIndexConfiguration(): array
    {
        return [
            'index' => static::searchableIndexName(),
            'body' => [
                'settings' => defined('static::INDEX_SETTINGS') ? static::INDEX_SETTINGS : new \stdClass(),
                'mappings' => array_filter([
                    '_source' => static::getSourceSettings(),
                    'dynamic' => defined('static::DYNAMIC_MAPPING') ? static::DYNAMIC_MAPPING : 'strict',
                    'properties' => static::getIndexProperties(),
                    'dynamic_templates' => static::getDynamicTemplates(),
                ]),
            ],
        ];
    }

    public static function getSourceSettings(): array
    {
        $settings = [];

        if (defined('static::SYNTHETIC_SOURCE') && true === static::SYNTHETIC_SOURCE) {
            $settings['mode'] = 'synthetic';
        }

        if (defined('static::DISABLES_SOURCE') && true === static::DISABLES_SOURCE) {
            $settings['enabled'] = false;
        }

        return $settings;
    }

    public static function getDynamicTemplates(): array
    {
        $properties = [];

        foreach (static::getElasticFields() as $key => $values) {
            foreach ($values as $value) {
                if (!empty($value->dynamic_template)) {
                    $properties[] = [
                        $value->name => [
                            'mapping' => [
                                'type' => $value->dynamic_template,
                            ],
                            'path_match' => $value->name.'.*',
                        ],
                    ];
                }
            }
        }

        return $properties;
    }

    public static function getTemplateConfiguration(): array
    {
        $configuration = static::getIndexConfiguration();

        $configuration['body'] = [
            'index_patterns' => $configuration['index'],
            'template' => $configuration['body'],
        ];

        return $configuration;
    }
}
