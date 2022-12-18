<?php

namespace Elastico\Console;

use App\Support\Digitalocean\Digitalocean;
use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class UpgradeElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:elasticsearch:upgrade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade Elasticsearch on a server ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

        $nodes = array_filter($nodes, fn ($node) => is_numeric(substr($node['name'], -2, 2)));

        $this->elastic = app()->make(Elasticsearch::class);

        // $this->elastic->indices()->flush(['index' => '*']);
        $this->call('system:elastic:shards:allocate', ['--disable' => true]);

        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            $process = $sshClient
                ->execute([
                    'systemctl stop elasticsearch.service',
                    'apt-get update && sudo apt-get --yes -o Dpkg::Options::="--force-confold" install elasticsearch ',
                    '/usr/share/elasticsearch/bin/elasticsearch-plugin remove repository-s3',
                    '/usr/share/elasticsearch/bin/elasticsearch-plugin install repository-s3 -b',
                    'systemctl start elasticsearch.service',
                ])
            ;

            $this->info($process->isSuccessful() ? 'success' : 'failed');
            $this->info($process->getOutput() ?: 'no output');
        }

        $this->call('system:elastic:shards:allocate', ['--enable' => true]);
    }
}
