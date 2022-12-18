<?php

namespace Elastico\Console;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallMetricbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:metricbeat:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Metricbeat on a server ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodes = [
            // '142.93.171.56' => '7zNmi7tqM0CcM73eGClu',  // Redis-1
            // //'68.183.77.204' => '', // 'elastic-1'

            '159.89.17.204' => '', // apm
            '159.89.105.255' => '', // kibana
            // '178.128.198.122' => 'lbludmH5tbRK5OxN4bme', // staging

            // '167.71.52.229' => '1jUnefPq9sbXSbaiWqF8', // web CH
            // '134.122.72.110' => 'ThK6RRxANBrliq4mts3j', // web 2
            // '167.71.34.61' => 's3yUqhovFxiTVCzhvlGe', // web 3

            // '159.89.108.24' => 'CbNMGP1fLgqlcK8eb7Q5', // worker 1
            // '206.81.24.217' => '0oD8LUTTKwyDz1Buxy3d', // worker 2

            // '167.172.99.165' => 'uMC37YyEjtZr5OUioupj', // websockets
            // '68.183.215.173' => '', // elasticsearch-01,
        ];

        foreach ($nodes as $nodeIp => $password) {
            $sshClient = Ssh::create('root', $nodeIp)
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;
            $this->password = $password;
            $response = $sshClient->upload(app_path('Monitoring/ElasticApm/Templates/metricbeat.yml'), '/tmp/metricbeat.yml');

            $process = $sshClient
                ->execute([
                    $this->changeToRoot(),
                    // $this->installMetricbeat(),
                    $this->copyMetricbeatConfig(),
                    $this->setMetricbeatConfigOwner(),
                    $this->addKeystorePassword(),
                    $this->enableModules(['elasticsearch-xpack']),
                    $this->startMetricbeat(),
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
                // "printf \"{$this->password}\n\" | sudo -S ls /etc/metricbeat",
            ]);
        }

        return '';
    }

    public function addKeystorePassword()
    {
        return 'echo "UsK2prFakRXhSPjTwyTQ" | sudo metricbeat keystore add --force --stdin elasticsearch_password';
    }

    public function installMetricbeat(): string
    {
        return implode("\n", [
            'curl -L -O https://artifacts.elastic.co/downloads/beats/metricbeat/metricbeat-7.15.2-amd64.deb',
            'sudo dpkg -i metricbeat-7.15.2-amd64.deb',
        ]);
    }

    public function copyMetricbeatConfig(): string
    {
        return 'sudo mv /tmp/metricbeat.yml /etc/metricbeat/metricbeat.yml';
    }

    public function enableModules(array $modules = []): string
    {
        if (empty($modules)) {
            return '';
        }

        return 'sudo metricbeat modules enable '.implode(' ', $modules);
    }

    public function startMetricbeat(): string
    {
        return implode("\n", [
            // 'sudo timeout 5 metricbeat',
            'sudo systemctl enable metricbeat',
            'sudo systemctl restart metricbeat',
        ]);
    }

    public function setMetricbeatConfigOwner(): string
    {
        return 'sudo chown root /etc/metricbeat/metricbeat.yml';
    }
}
