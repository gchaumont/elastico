<?php

namespace Elastico\Console\Security;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Spatie\Ssh\Ssh;

class UpdateCertificateAuthority extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:ca:update {hostname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup the host to trust the Certificate Authority';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->authorityPassword = 'Jdas872i3rjsfkas7efbhjmasdc';
        $this->certificatePassword = '8342rzhkSDHFBN84FHnaksmc01psDC341dx';
        $this->certAuthName = 'elastic-stack-http-ca.p12';
        $this->certAuthCert = 'elastic-stack-http-ca.crt';
        $this->certAuthPem = 'elastic-stack-http-ca.crt.pem';
        $this->elasticConfigLocation = '/etc/elasticsearch/elasticsearch.yml';
        $this->kibanaConfigLocation = '/etc/kibana/kibana.yml';
        $this->apmConfigLocation = '/etc/apm-server/apm-server.yml';

        //$this->trustedCertificatesLocation = '/usr/local/share/ca-certificates/elasticsearch';
        $this->trustedCertificatesLocation = '/usr/local/share/ca-certificates';

        // $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');
        $nodes = Digitalocean::getDroplets();

        $nodes = $nodes->filter(fn ($n) => $n['name'] == $this->argument('hostname'));

        $this->setupClientTrust($nodes);
    }

    public function setupClientTrust($clients)
    {
        foreach ($clients as $client) {
            $sshClient = Ssh::create($client['user'], $client['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;
            $sshClient->execute([
                'root' == $client['user'] ? '' : "printf \"{$client['password']}\n\" | sudo -S su -",
                "sudo mkdir {$this->trustedCertificatesLocation}",
                "sudo chmod 755 {$this->trustedCertificatesLocation}",
            ]);

            $sshClient->upload(
                storage_path("elastic/certificates/http/{$this->certAuthCert}"),
                "/tmp/{$this->certAuthCert}",
            );

            //$sshClient->upload(storage_path('elastic/certificates/elasticsearch-ssl-http/kibana/elasticsearch-ca.pem'), '/etc/kibana/elasticsearch-ca.pem');

            $sshClient->execute([
                'root' == $client['user'] ? '' : "printf \"{$client['password']}\n\" | sudo -S su -",

                "sudo mv /tmp/{$this->certAuthCert} {$this->trustedCertificatesLocation}/{$this->certAuthCert}",

                "sudo chmod 644 {$this->trustedCertificatesLocation}/{$this->certAuthCert}",

                'sudo update-ca-certificates',
            ]);
        }
    }
}
