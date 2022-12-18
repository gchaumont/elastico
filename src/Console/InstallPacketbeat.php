<?php

namespace Elastico\Console;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallPacketbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:packetbeat:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Packetbeat on a server ';

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

            // //'159.89.17.204' => '', // apm
            // // '159.89.105.255' => '', // kibana
            // '178.128.198.122' => 'lbludmH5tbRK5OxN4bme', // staging

            '167.71.52.229' => '1jUnefPq9sbXSbaiWqF8', // web CH
            '134.122.72.110' => 'ThK6RRxANBrliq4mts3j', // web 2
            '167.71.34.61' => 's3yUqhovFxiTVCzhvlGe', // web 3

            // '159.89.108.24' => 'CbNMGP1fLgqlcK8eb7Q5', // worker 1
            // '206.81.24.217' => '0oD8LUTTKwyDz1Buxy3d', // worker 2
            // '64.227.125.125' => '96TE4b348atywtSJfhnn', // crawler 1
            // '138.197.183.168' => '1AFpLsbwTqqbcqqyE2uu', // crawler 2

            // '167.172.99.165' => 'uMC37YyEjtZr5OUioupj', // websockets
            // '68.183.215.173' => '', // elasticsearch-01,
        ];

        foreach ($nodes as $nodeIp => $password) {
            $sshClient = Ssh::create('forge', $nodeIp)
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;
            $this->password = $password;
            $response = $sshClient->upload(app_path('Monitoring/ElasticApm/Templates/packetbeat.yml'), '/tmp/packetbeat.yml');

            $process = $sshClient
                ->execute([
                    $this->changeToRoot(),
                    $this->installPacketbeat(),
                    $this->copyPacketbeatConfig(),
                    $this->setPacketbeatConfigOwner(),
                    $this->addKeystorePassword(),
                    // $this->enableModules(['elasticsearch-xpack']),
                    // $this->createGeoipPipeline(),
                    $this->setupPacketbeatAssets(),
                    $this->startPacketbeat(),
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
                // "printf \"{$this->password}\n\" | sudo -S ls /etc/packetbeat",
            ]);
        }

        return '';
    }

    public function addKeystorePassword()
    {
        return 'echo "UsK2prFakRXhSPjTwyTQ" | sudo packetbeat keystore add --force --stdin elasticsearch_password';
    }

    public function installPacketbeat(): string
    {
        return implode("\n", [
            'sudo apt-get install libpcap0.8',
            'curl -L -O https://artifacts.elastic.co/downloads/beats/packetbeat/packetbeat-7.16.1-amd64.deb',
            'sudo dpkg -i packetbeat-7.16.1-amd64.deb',
        ]);
    }

    public function copyPacketbeatConfig(): string
    {
        return 'sudo mv /tmp/packetbeat.yml /etc/packetbeat/packetbeat.yml';
    }

    public function setupPacketbeatAssets(): string
    {
        return 'sudo packetbeat setup -e';
    }

    public function enableModules(array $modules = []): string
    {
        if (empty($modules)) {
            return '';
        }

        return 'sudo packetbeat modules enable '.implode(' ', $modules);
    }

    public function startPacketbeat(): string
    {
        return implode("\n", [
            // 'sudo timeout 5 packetbeat',
            'sudo systemctl enable packetbeat',
            'sudo systemctl restart packetbeat',
        ]);
    }

    public function setPacketbeatConfigOwner(): string
    {
        return 'sudo chown root /etc/packetbeat/packetbeat.yml';
    }
}
