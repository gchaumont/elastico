<?php

namespace Elastico\Console\Backups;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class Backup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:backup:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup Elasticsearch';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $elastic = resolve(Elasticsearch::class);

        // $elastic->snapsho()->createRepository([
        //     'repository' => 'elastic_backups',
        //     'body' => [
        //         'type' => 's3',
        //         'settings' => [
        //             'bucket' => 'batzoch',
        //             'base_path' => 'elasticsearch',
        //             'max_snapshot_bytes_per_sec' => '20mb',
        //         ]
        //     ]
        // ]);

        $r = $elastic->snapshot()->getRepository([
            'repository' => 'elastic_backups',
        ]);

        dump('getRepository', $r);

        $r = $elastic->snapshot()->verifyRepository([
            'repository' => 'elastic_backups',
        ]);

        dump('verifyRepository', $r);

        $r = $elastic->snapshot()->status([
            'repository' => 'elastic_backups',
            'snapshot' => '1',
        ]);
        dump('status', $r);

        $r = $elastic->snapshot()->get([
            'repository' => 'elastic_backups',
            'snapshot' => '_all',
        ]);
        dump('get', $r);

        $elastic->snapshot()->cleanupRepository([
            'repository' => 'elastic_backups',
        ]);
        dump('cleanupRepository', $r);

        $elastic->snapshot()->create([
            'repository' => 'elastic_backups',
            'snapshot' => date('Y-m-d'),
            // 'body' => [],
        ]);
    }
}
