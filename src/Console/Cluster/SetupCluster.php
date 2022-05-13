<?php

namespace Elastico\Console\Cluster;

use Illuminate\Console\Command;

class SetupCluster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:cluster:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an elasticsearch cluster';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->call('system:elastic:node:create', ['size' => 'droplet-2gb', 'role' => 'master']);
        $this->call('system:elastic:node:create', ['size' => 'droplet-16gb', 'role' => 'data_hot,data_content,ingest']);
        $this->call('system:elastic:node:create', ['size' => 'droplet-16gb', 'role' => 'data_hot,data_content,ingest']);

        $this->call('system:elastic:node:create ', ['size' => 'droplet-16gb', 'role' => 'data_warm']);
        $this->call('system:elastic:node:create ', ['size' => 'droplet-16gb', 'role' => 'data_warm']);

        $this->call('system:elastic:node:create ', ['size' => 'droplet-4gb', 'role' => '']);
        $this->call('system:elastic:node:create ', ['size' => 'droplet-4gb', 'role' => '']);

        $this->call('system:elastic:kibana:create', ['size' => 'droplet-2gb']);
        $this->call('system:elastic:apm:create', ['size' => 'droplet-2gb']);
    }
}
