<?php

namespace Elastico\Console\Nodes;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class ExcludeNodeAllocation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:allocation:exclude {nodename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'toggle shard allocation';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodename = $this->argument('nodename');

        $elastic = app()->make(Elasticsearch::class);

        $r = $elastic->cluster()->putSettings([
            'persistent' => [
                'cluster.routing.allocation.exclude._name' => $nodename ?: null,
            ],
        ]);

        dump($r);
    }
}
