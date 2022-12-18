<?php

namespace Elastico\Console;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallFleet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:fleet:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Fleet Agent on a server';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $esIp = '68.183.215.173';
        $esPrivateIp = '10.135.131.205';
        $nodes = [
            '159.89.17.204', // elastic APM
        ];

        // $this->generateCertificateAuthority($esIp, fleetIp: $nodes[0]);

        foreach ($nodes as $nodeIp) {
            $sshClient = Ssh::create('root', $nodeIp)
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            // $sshClient->upload(
            //     storage_path("elastic/certificates/http/elastic-stack-http-ca.crt"),
            //     "/usr/local/share/ca-certificates/elastic-stack-http-ca.crt",
            // );

            // $sshClient->execute([
            //     'sudo update-ca-certificates',
            //     'mkdir /etc/elastic-certs',
            // ]);

            $this->elasticCaCrt = '/etc/elastic-certs/elastic-stack-http-ca.crt';
            $this->fleetCaCrt = '/etc/elastic-certs/fleet-ca.crt';
            $this->fleetServerCrt = '/etc/elastic-certs/fleet-server.crt';
            $this->fleetServerKey = '/etc/elastic-certs/fleet-server.key';

            // $sshClient->upload(
            //     storage_path('elastic/certificates/http/elastic-stack-http-ca.crt'),
            //     $this->elasticCaCrt
            // );

            // $sshClient->upload(
            //     storage_path('elastic/certificates/fleet/ca/ca.crt'),
            //     $this->fleetCaCrt
            // );
            // $sshClient->upload(
            //     storage_path('elastic/certificates/fleet/fleet-certificates/fleet-server/fleet-server.crt'),
            //     $this->fleetServerCrt
            // );
            // $sshClient->upload(
            //     storage_path('elastic/certificates/fleet/fleet-certificates/fleet-server/fleet-server.key'),
            //     $this->fleetServerKey
            // );

            // $sshClient->upload(app_path('Monitoring/ElasticApm/Templates/elastic-agent.yml'), '/tmp/elastic-agent.yml');

            $process = $sshClient
                ->execute([
                    // $this->changeToRoot(),
                    // $this->prepareFleet(),
                    // $this->copyFleetConfig(),
                    // $this->addKeystorePassword(),
                    $this->installFleet($nodeIp, $esPrivateIp),
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

    public function prepareFleet(): string
    {
        return implode("\n", [
            'cd ~',
            'curl -L -O https://artifacts.elastic.co/downloads/beats/elastic-agent/elastic-agent-7.15.2-linux-x86_64.tar.gz',
            'tar xzvf elastic-agent-7.15.2-linux-x86_64.tar.gz',
            'cd elastic-agent-7.15.2-linux-x86_64',
        ]);
    }

    public function copyFleetConfig(): string
    {
        return 'sudo mv /tmp/elastic-agent.yml ~/elastic-agent-7.15.2-linux-x86_64/elastic-agent.yml';
    }

    public function installFleet($fleetip, $esPrivateIp): string
    {
        // return 'sudo ~/elastic-agent-7.15.2-linux-x86_64/elastic-agent install';

        // sudo ~/elastic-agent-7.15.2-linux-x86_64/elastic-agent install -f \n
        return " sudo ~/elastic-agent-7.15.2-linux-x86_64/elastic-agent enroll \\
            --url=http://{$fleetip}:80 \\
            -f \\
            --fleet-server-es=https://{$esPrivateIp}:9200 \\
            --fleet-server-es-ca=".$this->elasticCaCrt.' \\
            --fleet-server-service-token=AAEAAWVsYXN0aWMvZmxlZXQtc2VydmVyL3Rva2VuLTE2Mzk4NjExMjcwMDQ6c3BDckRVMkRRanFIdlVYZ0tDbGVYZw \\
            --fleet-server-policy=b3264460-499f-11ec-9ea9-89f3005326cd \\
            --certificate-authorities='.$this->fleetCaCrt.'\\
            --fleet-server-cert='.$this->fleetServerCrt.' \\
            --fleet-server-cert-key='.$this->fleetServerKey.' \\ 
            --insecure
            ';
    }

    public function generateCertificateAuthority($ip, $fleetIp)
    {
        $sshClient = Ssh::create('root', $ip)
            ->disableStrictHostKeyChecking()
            ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
            ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
        ;

        $sshClient->execute([
            'rm /tmp/fleet-ca.zip',
        ]);

        $sshClient->execute([
            '/usr/share/elasticsearch/bin/elasticsearch-certutil ca --pem --out=/tmp/fleet-ca.zip',
        ]);

        $sshClient->download('/tmp/fleet-ca.zip', storage_path('elastic/certificates/fleet/fleet-ca.zip'));

        exec('rm -r '.storage_path('elastic/certificates/fleet/ca '));
        exec('unzip '.storage_path('elastic/certificates/fleet/fleet-ca.zip').' -d '.storage_path('elastic/certificates/fleet'));

        $sshClient->upload(
            storage_path('elastic/certificates/fleet/ca/ca.crt'),
            '/tmp/fleet-ca.crt',
        );

        $sshClient->upload(
            storage_path('elastic/certificates/fleet/ca/ca.key'),
            '/tmp/fleet-ca.key',
        );

        $sshClient->execute([
            'rm /tmp/fleet-certificates.zip',
        ]);

        $sshClient->execute([
            "/usr/share/elasticsearch/bin/elasticsearch-certutil cert \\
              --name fleet-server \\
              --ca-cert /tmp/fleet-ca.crt \\
              --ca-key /tmp/fleet-ca.key \\
              --ip {$fleetIp} \\
              --pem \\
              --out=/tmp/fleet-certificates.zip
             ",
        ]);

        $sshClient->download('/tmp/fleet-certificates.zip', storage_path('elastic/certificates/fleet/fleet-certificates.zip'));

        $sshClient->execute([
            'rm /tmp/fleet-ca.crt',
            'rm /tmp/fleet-ca.key',
            'rm /tmp/fleet-ca.zip',
            'rm /tmp/fleet-ca.zip',
            'rm /tmp/fleet-certificates.zip',
        ]);

        exec('rm -r '.storage_path('elastic/certificates/fleet/fleet-certificates'));
        exec('unzip '.storage_path('elastic/certificates/fleet/fleet-certificates.zip').' -d '.storage_path('elastic/certificates/fleet/fleet-certificates'));
    }
}
