<?php

namespace Elastico\Models;

use Sushi\Sushi;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    use Sushi;

    protected $schema = [
        'id' => 'string',
        'type' => 'string',
        'settings' => 'json'
    ];

    public $incrementing = false;

    public function getCasts()
    {
        return [
            'settings' => 'array'
        ];
    }

    public function getRows(): array
    {
        return collect(
            DB::connection('elastic')
                ->getClient()
                ->snapshot()
                ->getRepository()
                ->asArray()
        )
            // ->dd()
            ->map(fn($value, $index) => [
                'id' => $index,
                'type' => $value['type'],
                'settings' => json_encode($value['settings'])
            ])
            ->values()
            ->all();
    }
}
