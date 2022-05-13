<?php

namespace Elastico\Console\Cluster;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;
use Throwable;

class ClusterHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:cluster:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get cluster Health Information';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $elastic = app()->make(Elasticsearch::class);

        try {
            $r = $elastic->cluster()->health();
        } catch (Throwable $e) {
            dd($e->getMessage());
        }

        //$r = $elastic->nodes()->info();
        //$r = $elastic->nodes()->stats();

        dump($r);
    }
}
