<?php

namespace Elastico\Console\Shards;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class ToggleShardAllocation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:shards:allocate {--enable} {--disable}';

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
        if ($this->option('enable')) {
            $enabled = null;
        } elseif ($this->option('disable')) {
            $enabled = 'primaries';
        } else {
            throw new \Exception('Enable or Disable option required');
        }

        $elastic = app()->make(Elasticsearch::class);

        $r = $elastic->cluster()->putSettings([
            'persistent' => [
                'cluster.routing.allocation.enable' => $enabled,
            ],
        ]);

        dump($r);
    }
}
