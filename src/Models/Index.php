<?php

namespace Elastico\Models;

use Sushi\Sushi;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Index extends Model
{
    use Sushi;

    protected $schema = [
        'timestamp' => 'timestamp',
        'name' => 'string',
        'cluster' => 'string',

        'primary_size' => 'integer',
        'total_size' => 'integer',
        'docs' => 'integer',
        'deleted_docs' => 'integer',
        'health' => 'string',
        'status' => 'string',
        'shards' => 'integer',
    ];

    public $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

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
                'cluster' => 'elastic',
                'total_size' => $value['total']['store']['size_in_bytes'],
                'primary_size' => $value['primaries']['store']['size_in_bytes'],
                'health' => $value['health'],
                'status' => $value['status'],
                'docs' => $value['total']['docs']['count'],
                'deleted_docs' => $value['total']['docs']['deleted'],
                'shards' => $value['total']['shard_stats']['total_count'],

            ])
            ->values()
            ->all();
    }
}
