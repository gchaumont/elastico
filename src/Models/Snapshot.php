<?php

namespace Elastico\Models;

use Sushi\Sushi;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
    use Sushi;

    protected $schema = [
        'snapshot' => 'string',
        'uuid' => 'string',
        'repository' => 'string',
        'version_id' => 'string',
        'indices' => 'json',
        'data_streams' => 'json',
        'include_global_state' => 'boolean',
        'state' => 'string',
        'start_time' => 'string',
        'start_time_in_millis' => 'integer',
        'end_time' => 'string',
        'end_time_in_millis' => 'integer',
        'duration_in_millis' => 'integer',
        'failures' => 'json',
        'shards' => 'json',
    ];

    public $primaryKey = 'snapshot';

    public $keyType = 'string';

    public $incrementing = false;

    public function getCasts()
    {
        return [
            'indices' => 'array',
            'data_streams' => 'array',
            'failures' => 'array',
            'shards' => 'array',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function getRows(): array
    {
        return Repository::all()
            ->flatMap(function ($repo): array {
                return collect(
                    DB::connection('elastic')
                        ->getClient()
                        ->snapshot()
                        ->get([
                            'repository' => $repo->id,
                            'snapshot' => '_all',
                        ])
                        ->asArray()['snapshots']
                )
                    ->map(fn($value, $index) => [
                        'snapshot' => $value['snapshot'],
                        'uuid' => $value['uuid'],
                        'repository' => $repo->id,
                        'version_id' => $value['version_id'],
                        'indices' => json_encode($value['indices']),
                        'data_streams' => json_encode($value['data_streams']),
                        'include_global_state' => $value['include_global_state'],
                        'state' => $value['state'],
                        'start_time' => $value['start_time'],
                        'start_time_in_millis' => $value['start_time_in_millis'],
                        'end_time' => $value['end_time'],
                        'end_time_in_millis' => $value['end_time_in_millis'],
                        'duration_in_millis' => $value['duration_in_millis'],
                        'failures' => json_encode($value['failures']),
                        'shards' => json_encode($value['shards']),
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }
}
