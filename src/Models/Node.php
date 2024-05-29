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

        'total_refreshes' => 'integer', // * Total refreshes (Count)
        'total_refreshes_time_ms' => 'integer', // * Total time spent refreshing (Milliseconds)
        'current_merges' => 'integer', // * Current merges (Count)
        'total_merges' => 'integer', // * Total merges (Count)
        'total_merge_time_ms' => 'integer', // * Total time spent merging (Milliseconds)

        'filesystem_total' => 'integer',
        'filesystem_available_bytes' => 'integer',
        'filesystem_used_bytes' => 'integer',
        'filesystem_used_percent' => 'integer',
        'memory_current_percent' => 'integer',
        'memory_current_bytes' => 'integer',
        'memory_max_bytes' => 'integer',
        'cpu' => 'integer',
        'cpu_load_1m' => 'integer',
        'cpu_load_5m' => 'integer',
        'cpu_load_15m' => 'integer',

        'jvm_heap_used_in_bytes' => 'integer',
        'jvm_heap_used_percent' => 'integer',
        'jvm_heap_max_in_bytes' => 'integer',
        'jvm_non_heap_used_in_bytes' => 'integer',
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

    public function getRows(): array
    {
        return collect(DB::connection('elastic')
            ->getClient()
            ->nodes()
            ->stats()
            ->asArray()['nodes'])
            ->map(
                static fn (array $value, string $node) => [
                    'name' => $value['name'],
                    'cluster' => 'elastic',

                    'total_refreshes' => $value['indices']['refresh']['total'], // * Total refreshes (Count)
                    'total_refreshes_time_ms' => $value['indices']['refresh']['total_time_in_millis'], // * Total time spent refreshing (Milliseconds)
                    'current_merges' => $value['indices']['merges']['current'], // * Current merges (Count)
                    'total_merges' => $value['indices']['merges']['total'], // * Total merges (Count)
                    'total_merge_time_ms' => $value['indices']['merges']['total_time_in_millis'], // * Total time spent merging (Milliseconds)

                    'filesystem_total' => $value['fs']['total']['total_in_bytes'],
                    'filesystem_available_bytes' => $value['fs']['total']['available_in_bytes'],
                    'filesystem_used_bytes' => $usedBytes = ($value['fs']['total']['total_in_bytes'] - $value['fs']['total']['available_in_bytes']),
                    'filesystem_used_percent' => round(100 * $usedBytes / $value['fs']['total']['total_in_bytes']),
                    'memory_current_percent' => $value['os']['mem']['used_percent'],
                    'memory_current_bytes' => $value['os']['mem']['used_in_bytes'],
                    'memory_max_bytes' => $value['os']['mem']['total_in_bytes'],
                    'cpu' => $value['os']['cpu']['percent'],
                    'cpu_load_1m' => $value['os']['cpu']['load_average']['1m'],
                    'cpu_load_5m' => $value['os']['cpu']['load_average']['5m'],
                    'cpu_load_15m' => $value['os']['cpu']['load_average']['15m'],

                    'jvm_heap_used_in_bytes' => $value['jvm']['mem']['heap_used_in_bytes'],
                    'jvm_heap_used_percent' => $value['jvm']['mem']['heap_used_percent'],
                    'jvm_heap_max_in_bytes' => $value['jvm']['mem']['heap_max_in_bytes'],
                    'jvm_non_heap_used_in_bytes' => $value['jvm']['mem']['non_heap_used_in_bytes'],
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
