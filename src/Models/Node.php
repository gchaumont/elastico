<?php

namespace Elastico\Models;

use Sushi\Sushi;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Node extends Model
{

    use Sushi;

    protected $schema = [
        'name' => 'string',
        'cluster' => 'string',

        'fs' => 'json',
        'os' => 'json',
        'jvm' => 'json',
        'indices' => 'json',

        'total_refreshes' => 'integer', // * Total refreshes (Count)
        'total_refreshes_time_ms' => 'integer', // * Total time spent refreshing (Milliseconds)
        'current_merges' => 'integer', // * Current merges (Count)
        'total_merges' => 'integer', // * Total merges (Count)
        'total_merge_time_ms' => 'integer', // * Total time spent merging (Milliseconds)


        'threads_count' => 'integer',
        'threads_peak_count' => 'integer',
        'garbage_collection_young_collection_count' => 'integer',
        'garbage_collection_young_collection_time_ms' => 'integer',
        'garbage_collection_old_collection_count' => 'integer',
        'garbage_collection_old_collection_time_ms' => 'integer',

        'http_current_open' => 'integer',
        'http_total_opened' => 'integer',

    ];

    public $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

    public function getCasts()
    {
        return [
            'fs' => 'array',
            'os' => 'array',
            'jvm' => 'array',
            'indices' => 'array',
        ];
    }

    public function getRows(): array
    {
        return collect(DB::connection('elastic')
            ->getClient()
            ->nodes()
            ->stats()
            ->asArray()['nodes'])
            ->map(
                static fn(array $value, string $node) => [
                    'name' => $value['name'],
                    'cluster' => 'elastic',

                    'fs' => json_encode($value['fs']),
                    'os' => json_encode($value['os']),
                    'jvm' => json_encode($value['jvm']),
                    'indices' => json_encode($value['indices']),

                    'total_refreshes' => $value['indices']['refresh']['total'], // * Total refreshes (Count)
                    'total_refreshes_time_ms' => $value['indices']['refresh']['total_time_in_millis'], // * Total time spent refreshing (Milliseconds)
                    'current_merges' => $value['indices']['merges']['current'], // * Current merges (Count)
                    'total_merges' => $value['indices']['merges']['total'], // * Total merges (Count)
                    'total_merge_time_ms' => $value['indices']['merges']['total_time_in_millis'], // * Total time spent merging (Milliseconds)

                    'threads_count' => $value['jvm']['threads']['count'],
                    'threads_peak_count' => $value['jvm']['threads']['peak_count'],
                    'garbage_collection_young_collection_count' => $value['jvm']['gc']['collectors']['young']['collection_count'],
                    'garbage_collection_young_collection_time_ms' => $value['jvm']['gc']['collectors']['young']['collection_time_in_millis'],
                    'garbage_collection_old_collection_count' => $value['jvm']['gc']['collectors']['old']['collection_count'],
                    'garbage_collection_old_collection_time_ms' => $value['jvm']['gc']['collectors']['old']['collection_time_in_millis'],

                    'http_current_open' => $value['http']['current_open'],
                    'http_total_opened' => $value['http']['total_opened'],
                ]
            )
            ->values()
            ->all();
    }
}
