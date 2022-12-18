<?php

namespace Elastico\Console;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallElasticAgent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:agent:install {hostname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Elastic Agent on a server';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

//        $nodes = array_filter($nodes, fn ($node) => is_numeric(substr($node['name'], -2, 2)));

        $nodes = array_filter($nodes, fn ($node) => $node['name'] == $this->argument('hostname'));

        // curl -f http://159.89.17.204:8820/api/status

        foreach ($nodes as $node) {
            $this->password = ''; // $password;
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            $process = $sshClient
                ->execute([
                    // $this->changeToRoot(),
                    $this->prepareFleet(),
                ])
            ;

            $this->info($process->isSuccessful() ? 'success' : 'failed');
            $this->info($process->getOutput() ?: 'no output');
        }
    }

    public function changeToRoot(): string
    {
        if ($this->password) {
            return implode("\n", [
                "printf \"{$this->password}\n\" | sudo -S su -",
                // "printf \"{$this->password}\n\" | sudo -S ls /etc/filebeat",
            ]);
        }

        return '';
    }

    public function prepareFleet(): string
    {
        return implode("\n", [
            'cd ~',
            'curl -L -O https://artifacts.elastic.co/downloads/beats/elastic-agent/elastic-agent-7.16.2-linux-x86_64.tar.gz',
            'tar xzvf elastic-agent-7.16.2-linux-x86_64.tar.gz',
            'cd elastic-agent-7.16.2-linux-x86_64',
            'sudo ./elastic-agent install -f --url=https://159.89.17.204:8220 --enrollment-token=aXpuZXpYMEJENlJDM0xYd1JQMTg6SzVRc2ZfQVFTYWl4anVIRG9IcE5rdw== --insecure',
        ]);
    }
}
