<?php

namespace Elastico\Console;

use App\Support\Digitalocean\Digitalocean;
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
        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch')
            ->filter(fn ($node) => is_numeric(substr($node['name'], -2, 2)))
        ;

        // $this->elastic->indices()->flush(['index' => '*']);
        $this->call('elastic:shards:allocate', ['--disable' => true]);

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
                    'systemctl start elasticsearch.service',
                ])
            ;

            $this->info($process->isSuccessful() ? 'success' : 'failed');
            $this->info($process->getOutput() ?: 'no output');
        }

        $this->call('elastic:shards:allocate', ['--enable' => true]);
    }
}
