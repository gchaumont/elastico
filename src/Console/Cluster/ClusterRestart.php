<?php

namespace Elastico\Console\Cluster;

use App\Support\Digitalocean\Digitalocean;
use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class ClusterRestart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:cluster:restart';

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

        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

        $nodes = array_values(array_filter($nodes, fn ($n) => is_numeric(substr($n['name'], -2, 2))));

        $this->call('system:elastic:shards:allocate', ['--disable' => true]);

        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info(strtoupper($type).' - '.$line))
            ;

            $sshClient->execute(['systemctl restart elasticsearch']);
        }

        $this->call('system:elastic:shards:allocate', ['--enable' => true]);
    }
}
