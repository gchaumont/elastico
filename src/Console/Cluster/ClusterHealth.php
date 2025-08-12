<?php

namespace Elastico\Console\Cluster;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClusterHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:cluster:health
        {--connection=elastic : The DB Connection }
    ';

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
        $elastic = DB::connection('elastic')->getClient();

        try {
            $r = $elastic->cluster()->health();
        } catch (Throwable $e) {
            dd($e->getMessage());
        }

        // $r = $elastic->nodes()->info();
        // $r = $elastic->nodes()->stats();

        dump($r->asArray());
    }
}
