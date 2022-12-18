<?php

namespace Elastico\Console\Security;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Spatie\Ssh\Ssh;

class CopyCertificate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:certificate:copy {path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy the Elasticsearch Http Certificate to the given path';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    }

    public function setupApmTrust($nodes)
    {
        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            $sshClient->upload(
                storage_path('elastic/certificates/http/'.$this->certAuthPem),
                "/tmp/{$this->certAuthPem}",
            );

            $sshClient->execute(
                array_merge(
                    [
                        "mv /tmp/{$this->certAuthPem} /etc/apm-server/{$this->certAuthPem}",
                        "sudo chown apm-server /etc/apm-server/{$this->certAuthPem}",
                    ],
                    array_map(
                        fn ($setting) => "grep -qxF '{$setting}' {$this->apmConfigLocation} || echo '{$setting}' >> {$this->apmConfigLocation}",
                        [
                            "output.elasticsearch.ssl.certificateAuthorities: [\"{$this->certAuthPem}\"]",
                        ]
                    ),
                    ['systemctl restart apm-server']
                )
            );
        }
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

            // $sshClient->upload(storage_path('elastic/certificates/elasticsearch-ssl-http/kibana/elasticsearch-ca.pem'), '/etc/kibana/elasticsearch-ca.pem');

            $sshClient->execute([
                'root' == $client['user'] ? '' : "printf \"{$client['password']}\n\" | sudo -S su -",

                "sudo mv /tmp/{$this->certAuthCert} {$this->trustedCertificatesLocation}/{$this->certAuthCert}",

                "sudo chmod 644 {$this->trustedCertificatesLocation}/{$this->certAuthCert}",

                'sudo update-ca-certificates',
            ]);
        }
    }

    public function generateCertificates($masterNode, $nodes)
    {
        $sshClient = Ssh::create('root', $masterNode['public_ip'])
            ->disableStrictHostKeyChecking()
            ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
            ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
        ;

        $sshClient->upload(
            storage_path('elastic/certificates/http/'.$this->certAuthName),
            "/tmp/{$this->certAuthName}"
        );

        $yaml = "instances:\n";

        foreach ($nodes as $node) {
            $nodename = $node['name'];
            $nodePrivateIP = $node['private_ip'];
            $yaml .= " - name: {$nodename}\n";
            $yaml .= "   ip: \n     - {$nodePrivateIP}\n";

            $yaml .= "   dns: \n     - localhost\n";
        }

        $tmpfile = tmpfile();

        // $contents = file_get_contents(storage_path('elastic/config/elasticsearch.yml'));

        // $contents = str_replace('{{NODE_LOCAL_IP}}', $nodeIp, $contents);

        fwrite($tmpfile, $yaml);

        // $sshClient->upload(stream_get_meta_data($tmpfile)['uri'], '/etc/elasticsearch/instances.yml');
        $sshClient->upload(stream_get_meta_data($tmpfile)['uri'], '/tmp/instances.yml');

        fclose($tmpfile);

        $sshClient->execute([
            "sudo /usr/share/elasticsearch/bin/elasticsearch-certutil cert --in /tmp/instances.yml --out /tmp/nodecerts.zip --ca /tmp/{$this->certAuthName} --ca-pass {$this->authorityPassword} --pass {$this->certificatePassword}",
        ]);

        exec('rm '.storage_path('elastic/certificates/http/nodecerts.zip'));
        exec('rm -r '.storage_path('elastic/certificates/http/nodecerts'));

        $sshClient->download('/tmp/nodecerts.zip', storage_path('elastic/certificates/http/nodecerts.zip'));
        $sshClient->execute([
            'rm /tmp/nodecerts.zip',
            'rm /tmp/instances.yml',
            "rm /tmp/{$this->certAuthName}",
        ]);

        exec('unzip '.storage_path('elastic/certificates/http/nodecerts.zip').' -d '.storage_path('elastic/certificates/http/nodecerts'));
        exec('rm '.storage_path('elastic/certificates/http/nodecerts.zip'));
    }

    public function secureHttpTraffic($nodes)
    {
        foreach ($nodes as $node) {
            $this->info('Securing Traffic on '.$node['name']);
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info(strtoupper($type).' - '.$line))
            ;

            $nodeName = $node['name'];

            $sshClient->upload(
                storage_path("elastic/certificates/http/nodecerts/{$nodeName}/{$nodeName}.p12"),
                '/etc/elasticsearch/http.p12'
            );

            $process = $sshClient
                ->execute(
                    array_merge(
                        [
                            'sudo chown elasticsearch /etc/elasticsearch/http.p12',
                            "sed -i '/xpack.security.http.ssl.client_authentication: required/c\\xpack.security.http.ssl.client_authentication: optional' {$this->elasticConfigLocation}",
                        ],
                        array_map(
                            // APPEND IF NOT EXISTS
                            fn ($setting) => "grep -qxF '{$setting}' {$this->elasticConfigLocation} || echo '{$setting}' >> {$this->elasticConfigLocation}",
                            [
                                'xpack.security.http.ssl.enabled: true',
                                'xpack.security.http.ssl.verification_mode: certificate',
                                'xpack.security.http.ssl.client_authentication: optional',
                                'xpack.security.http.ssl.keystore.path: http.p12',
                            ]
                        ),
                        [
                            "echo \"{$this->certificatePassword}\" | /usr/share/elasticsearch/bin/elasticsearch-keystore add -f --stdin xpack.security.http.ssl.keystore.secure_password",
                            // "echo \"{$this->certificatePassword}\" | /usr/share/elasticsearch/bin/elasticsearch-keystore add -f --stdin xpack.security.http.ssl.truststore.secure_password",
                            'systemctl restart elasticsearch',
                        ]
                    ),
                )
            ;
        }
    }

    public function generateCertificateAutority($node)
    {
        if (
            file_exists(storage_path("elastic/certificates/http/{$this->certAuthCert}"))
            && file_exists(storage_path("elastic/certificates/http/{$this->certAuthName}"))
            && file_exists(storage_path("elastic/certificates/http/{$this->certAuthPem}"))
        ) {
            return;
        }

        $sshClient = Ssh::create('root', $node['public_ip'])
            ->disableStrictHostKeyChecking()
            ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
            ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
        ;

        $days = 365 * 5;

        $sshClient->execute([
            "/usr/share/elasticsearch/bin/elasticsearch-certutil ca --days={$days} --pass={$this->authorityPassword} --out={$this->certAuthName}",
            "openssl pkcs12 -in /usr/share/elasticsearch/{$this->certAuthName} -out /usr/share/elasticsearch/{$this->certAuthCert} -nokeys -passin pass:{$this->authorityPassword}",
            "openssl pkcs12 -in /usr/share/elasticsearch/{$this->certAuthName} -out /usr/share/elasticsearch/{$this->certAuthPem} -clcerts -nokeys -passin pass:{$this->authorityPassword}",
        ]);

        $sshClient->download(
            "/usr/share/elasticsearch/{$this->certAuthName}",
            storage_path("elastic/certificates/http/{$this->certAuthName}")
        );

        $sshClient->download(
            "/usr/share/elasticsearch/{$this->certAuthCert}",
            storage_path("elastic/certificates/http/{$this->certAuthCert}")
        );

        $sshClient->download(
            "/usr/share/elasticsearch/{$this->certAuthPem}",
            storage_path("elastic/certificates/http/{$this->certAuthPem}")
        );

        $sshClient->execute([
            "rm /usr/share/elasticsearch/{$this->certAuthName}",
            "rm /usr/share/elasticsearch/{$this->certAuthCert}",
            "rm /usr/share/elasticsearch/{$this->certAuthPem}",
        ]);
    }
}
