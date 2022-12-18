<?php

namespace Elastico\Console\Backups;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class CleanBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:backup:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old Elasticsearch Backups';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $elastic = resolve(Elasticsearch::class);

        $r = $elastic->snapshot()->getRepository([
            'repository' => 'elastic_backups',
        ]);

        // dump('getRepository', $r);

        $r = $elastic->snapshot()->verifyRepository([
            'repository' => 'elastic_backups',
        ]);

        // dump('verifyRepository', $r);

        $r = $elastic->snapshot()->status([
            'repository' => 'elastic_backups',
            'snapshot' => '1',
        ]);
        // dump('status', $r);

        $r = $elastic->snapshot()->get([
            'repository' => 'elastic_backups',
            'snapshot' => '_all',
        ]);
        // dump('get', $r);

        foreach (array_reverse($r['snapshots']) as $snapshot) {
            // if ($snapshot['state'] !== 'SUCCESS') {
            //     throw new \Exception("Unsuccessful backup", 1);
            // }
            $time = \Carbon\Carbon::parse($snapshot['start_time']);

            if ($time > now()->subDays(7)) {
                continue;
            }

            if ($time < now()->subDays(7) && $time > now()->subDays(30 * 1) && '01' == $time->format('d')) {
                continue;
            }

            if ($time < now()->subDays(7) && $time > now()->subDays(30 * 1) && '15' == $time->format('d')) {
                continue;
            }

            dump('deleting'.$snapshot['snapshot']);
            $elastic->snapshot()->delete([
                'repository' => 'elastic_backups',
                'snapshot' => $snapshot['snapshot'],
            ]);
        }

        $elastic->snapshot()->cleanupRepository([
            'repository' => 'elastic_backups',
        ]);
        dump('cleanupRepository', $r);
    }
}
