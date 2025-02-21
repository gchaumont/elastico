<?php

namespace Elastico\Models;

use Sushi\Sushi;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Index extends Model
{
    use Sushi;

    protected $schema = [
        'uuid' => 'string',
        'name' => 'string',

        'health' => 'string',
        'status' => 'string',

        'primaries' => 'json',
        'total' => 'json',

        'timestamp' => 'timestamp',
    ];

    public $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

    public function getCasts()
    {
        return [
            'primaries' => 'array',
            'total' => 'array',
        ];
    }

    public function getSettings()
    {
        return DB::connection('elastic')
            ->getClient()
            ->indices()
            ->getSettings(['index' => $this->name])
            ->asArray();
    }

    public function getMappings()
    {
        return DB::connection('elastic')
            ->getClient()
            ->indices()
            ->getMapping(['index' => $this->name])
            ->asArray();
    }


    public function getRows(): array
    {
        return collect(DB::connection('elastic')
            ->getClient()
            ->indices()
            ->stats()
            ->asArray()['indices'])
            ->map(fn($value, $index) => [
                'name' => $index,
                'uuid' => $value['uuid'],
                'primaries' => json_encode($value['primaries']),
                'total' => json_encode($value['total']),
                'timestamp' => now(),
                'health' => $value['health'],
                'status' => $value['status'],
            ])
            ->values()
            ->all();
    }
}
