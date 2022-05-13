<?php

namespace Elastico\Console;

use Illuminate\Console\Command;

class CreateElasticsearchNode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:node:make {hostname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare server to run elasticsearch';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->call('system:elastic:elasticsearch:install', ['hostname' => $this->argument('hostname')]);
        $this->call('system:elastic:tls:transport', ['hostname' => $this->argument('hostname')]);
        $this->call('system:elastic:tls:http', ['hostname' => $this->argument('hostname')]);
        //$this->call('system:elastic:agent:install', ['hostname' => $this->argument('hostname')]);
        //$this->call('system:elastic:filebeat:install', ['hostname' => $this->argument('hostname')]);
    }
}
