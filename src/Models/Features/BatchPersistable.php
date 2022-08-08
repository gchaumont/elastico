<?php

namespace Elastico\Models\Features;

use Exception;
use GuzzleHttp\Ring\Future\FutureArray;
use Illuminate\Support\Collection;

trait BatchPersistable
{
    /**
     * Updates models with IDs and creates models without IDs.
     */
    public static function saveBatch(iterable $objects, array $source = null)
    {
        $objects = collect($objects)->values();

        if ($objects->isEmpty()) {
            return $objects;
        }

        $payload = [];

        foreach ($objects as $model) {
            if (empty($model->get_id())) {
                $payload['body'][] = [
                    'create' => [
                        '_index' => $model->writableIndexName(),
                    ],
                ];
                $payload['body'][] = $model->serialise();
            } else {
                $payload['body'][] = [
                    'update' => [
                        '_id' => $model->get_id(),
                        '_index' => $model->writableIndexName(),
                    ],
                ];
                $payload['body'][] = array_filter([
                    'doc_as_upsert' => true,
                    'doc' => $model->serialise(),
                    '_source' => $source,
                ]);
            }
        }

        $response = static::query()->bulk($payload);

        static::handleBulkError($response);

        return static::hydrateModelsFromSource($objects->all(), $response['items']);
    }

    public static function insertBatch($objects)
    {
        $body = [];

        foreach ($objects as $model) {
            $body[] = [
                'create' => [
                    '_index' => $model->writableIndexName(),
                ],
            ];
            $body[] = $model->serialise();
        }

        $response = static::query()->bulk(['body' => $body]);

        static::handleBulkError($response);

        return $response;
    }

    public static function upsertBatch(iterable $objects, array $source = null, bool $proceed = false): Collection
    {
        $objects = collect($objects)->values();

        if ($objects->isEmpty()) {
            return $objects;
        }

        $payload = $objects->flatMap(fn ($model) => [
            [
                'update' => [
                    '_id' => $model->get_id(),
                    '_index' => $model->writableIndexName(),
                ],
            ],
            array_filter([
                'doc_as_upsert' => true,
                'doc' => $model->serialise(),
                '_source' => $source,
            ]),
        ])
            ->all()
        ;

        $response = static::query()->bulk(['body' => $payload]);

        if (!$proceed) {
            static::handleBulkError($response);
        }

        return static::hydrateModelsFromSource($objects->all(), $response['items']);
    }

    public static function deleteBatch(array $objects)
    {
        if (empty($objects)) {
            return [];
        }

        $payload = [];
        foreach ($objects as $model) {
            $payload['body'][] = [
                'delete' => [
                    '_id' => $model->get_id(),
                    '_index' => $model->writableIndexName(),
                ],
            ];
        }

        $response = static::query()->bulk($payload);

        static::handleBulkError($response);

        return $response;
    }

    private static function handleBulkError(array|FutureArray $response): void
    {
        $handleError = function (array|FutureArray $response) {
            if ($response['errors']) {
                throw new Exception('Indexing error '.json_encode($response), 1);
            }
        };

        if ($response instanceof FutureArray) {
            $response->then(
                fn () => $handleError($response),
                fn () => $handleError($response)
            );
        } else {
            $handleError($response);
        }
    }

    private static function hydrateModelsFromSource(array $objects, array $items): Collection
    {
        foreach ($items as $key => $result) {
            $actionType = array_key_first($result);

            if (!empty($objects[$key]->get_id())) {
                if (($result[$actionType]['_id']) !== $objects[$key]->get_id()) {
                    throw new Exception('Upsert Model Hydration Mismatch', 1);
                }
            } else {
                $objects[$key]->id = $result[$actionType]['_id'];
            }

            if (!empty($result[$actionType]['get']['_source'])) {
                $objects[$key]->addSerialisedData($result[$actionType]['get']['_source']);
            }
            $objects[$key]->upsert_result = $result[$actionType]['result'] ?? 'error';
            $objects[$key]->upsert_index = $result[$actionType]['_index'];
        }

        return collect($objects);
    }
}
