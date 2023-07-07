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
    protected $signature = 'elastic:elasticsearch:upgrade
                        {ip : The SSH IP Adress} 
                        {--user=root : The SSH User} 
                        {--root-password= : The password for the RootUser} 
                        {--private-key= : The path to the SSH Private Key } 
                        {--port=22 : The SSH Port }
    ';

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
        // $this->elastic->indices()->flush(['index' => '*']);
        $this->call('elastic:shards:allocate', ['--disable' => true]);

        $sshClient = Ssh::create('root', $this->argument('ip'))
            ->disableStrictHostKeyChecking()
            ->usePrivateKey($this->option('private-key'))
            ->onOutput(fn ($type, $line) => $this->info($type . ' ' . $line));

        $process = $sshClient
            ->execute([
                'systemctl stop elasticsearch.service',
                'apt-get update && sudo apt-get --yes -o Dpkg::Options::="--force-confold" install elasticsearch ',
                'systemctl start elasticsearch.service',
            ]);

        $this->info($process->isSuccessful() ? 'success' : 'failed');
        $this->info($process->getOutput() ?: 'no output');


        $this->call('elastic:shards:allocate', ['--enable' => true]);
    }
}
