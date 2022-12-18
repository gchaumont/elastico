<?php

namespace Elastico\Console;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallFilebeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:filebeat:install {hostname?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Filebeat on a server ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

        if ($this->argument('hostname')) {
            $nodes = array_filter($nodes, fn ($node) => $node['name'] == $this->argument('hostname'));
        }

        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;
            $this->password = $node['password'] ?? null;

            $response = $sshClient->upload(app_path('Monitoring/ElasticApm/Templates/filebeat.yml'), '/tmp/filebeat.yml');

            $process = $sshClient
                ->execute([
                    $this->changeToRoot(),
                    // $this->installFilebeat(),
                    // $this->copyFilebeatConfig(),
                    // $this->setFilebeatConfigOwner(),
                    // $this->addKeystorePassword(),
                    // $this->enableModules(['elasticsearch']),
                    $this->startFilebeat(),
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

    public function addKeystorePassword()
    {
        return 'echo "UsK2prFakRXhSPjTwyTQ" | sudo filebeat keystore add --force --stdin elasticsearch_password';
    }

    public function installFilebeat(): string
    {
        return implode("\n", [
            'curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-7.15.2-amd64.deb',
            'sudo dpkg -i filebeat-7.15.2-amd64.deb',
        ]);
    }

    public function copyFilebeatConfig(): string
    {
        return 'sudo mv /tmp/filebeat.yml /etc/filebeat/filebeat.yml';
    }

    public function enableModules(array $modules = []): string
    {
        if (empty($modules)) {
            return '';
        }

        return 'sudo filebeat modules enable '.implode(' ', $modules);
    }

    public function startFilebeat(): string
    {
        return implode("\n", [
            // 'sudo timeout 5 filebeat',
            'sudo systemctl enable filebeat',
            'sudo systemctl restart filebeat',
            // 'sudo systemctl stop filebeat',
            // 'sudo systemctl stop metricbeat',
        ]);
    }

    public function setFilebeatConfigOwner(): string
    {
        return 'sudo chown root /etc/filebeat/filebeat.yml';
    }
}
