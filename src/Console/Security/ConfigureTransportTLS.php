<?php

namespace Elastico\Console\Security;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class ConfigureTransportTLS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:tls:transport {hostname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure elasticsearch tls between cluster nodes';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->authorityPassword = 'aSDKF213l34jnaluth4qoi4UTLNF23LNALsfjkn';
        $this->authorityPassword = 'Jdas872i3rjsfkas7efbhjmasdc';
        $this->certificatePassword = '8342rzhkSDHFBN84FHnaksmc01psDC341dx';
        $this->certAuthName = 'elastic-stack-ca.p12';
        $this->transportCertificate = 'elastic-certificates.p12';
        $this->esHome = '/usr/share/elasticsearch';
        $this->elasticConfigLocation = '/etc/elasticsearch/elasticsearch.yml';

        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

        $masterNode = array_values(array_filter($nodes, fn ($n) => is_numeric(substr($n['name'], -2, 2))))[1];

        $nodes = array_filter($nodes, fn ($n) => $n['name'] == $this->argument('hostname'));

        $this->generateCertificateAutority($masterNode); // on one node then copy certificate all others

        $this->generateTransportCertificate($masterNode);

        $this->encriptInternodeCommunication($nodes);
    }

    public function encriptInternodeCommunication($nodes)
    {
        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            $sshClient->upload(
                storage_path("elastic/certificates/transport/{$this->transportCertificate}"),
                "/etc/elasticsearch/{$this->transportCertificate}"
            );

            $sshClient
                ->execute(
                    array_merge(
                        $this->configureElasticYML(),
                        [
                            "chown elasticsearch /etc/elasticsearch/{$this->transportCertificate}",

                            "echo \"{$this->certificatePassword}\" | {$this->esHome}/bin/elasticsearch-keystore add -f --stdin xpack.security.transport.ssl.keystore.secure_password",
                            "echo \"{$this->certificatePassword}\" | {$this->esHome}/bin/elasticsearch-keystore add -f --stdin xpack.security.transport.ssl.truststore.secure_password",

                            'systemctl restart elasticsearch.service',
                        ]
                    )
                )
            ;
        }
    }

    public function generateTransportCertificate($node)
    {
        if (file_exists(storage_path("elastic/certificates/transport/{$this->transportCertificate}"))) {
            return;
        }

        $sshClient = Ssh::create('root', $node['public_ip'])
            ->disableStrictHostKeyChecking()
            ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
            ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

        $sshClient->upload(
            storage_path("elastic/certificates/transport/{$this->certAuthName}"),
            "{$this->esHome}/{$this->certAuthName}",
        );

        $sshClient->execute([
            "{$this->esHome}/bin/elasticsearch-certutil cert --ca {$this->certAuthName} --ca-pass {$this->authorityPassword} --pass {$this->certificatePassword} -s --out {$this->transportCertificate}",
        ]);

        $sshClient->download(
            "{$this->esHome}/{$this->transportCertificate}",
            storage_path("elastic/certificates/transport/{$this->transportCertificate}")
        );

        $sshClient->execute([
            "rm {$this->esHome}/{$this->transportCertificate}",
        ]);
    }

    public function generateCertificateAutority($node)
    {
        if (file_exists(storage_path("elastic/certificates/transport/{$this->certAuthName}"))) {
            return;
        }

        $sshClient = Ssh::create('root', $node['public_ip'])
            ->disableStrictHostKeyChecking()
            ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
            ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

        $days = 365 * 5;

        $sshClient->execute([
            "{$this->esHome}/bin/elasticsearch-certutil ca --days={$days} --pass={$this->authorityPassword} --out={$this->certAuthName}",
        ]);

        $response = $sshClient->download(
            "{$this->esHome}/{$this->certAuthName}",
            storage_path("elastic/certificates/transport/{$this->certAuthName}")
        );

        $sshClient->execute([
            "rm {$this->esHome}/{$this->certAuthName}",
        ]);
    }

    public function configureElasticYML()
    {
        return array_map(
            // APPEND IF NOT EXISTS
            fn ($setting) => "grep -qxF '{$setting}' {$this->elasticConfigLocation} || echo '{$setting}' >> {$this->elasticConfigLocation}",
            [
                'xpack.security.enabled: true',
                'xpack.security.transport.ssl.enabled: true',
                'xpack.security.transport.ssl.verification_mode: certificate ',
                'xpack.security.transport.ssl.client_authentication: required',
                "xpack.security.transport.ssl.keystore.path: {$this->transportCertificate}",
                "xpack.security.transport.ssl.truststore.path: {$this->transportCertificate}",
            ]
        );

        // APPEND TO FILE "echo \"{$xpackSecureConfig}\" >> $this->elasticConfigLocation",
    }
}
