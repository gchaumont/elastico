<?php

namespace Elastico\Console\Security;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Spatie\Ssh\Ssh;

class ConfigureHttpTLS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:tls:http {hostname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure elasticsearch tls between cluster and clients';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
//        $this->call('system:elastic:shards:allocate', ['--disable' => true]);

        $this->authorityPassword = 'Jdas872i3rjsfkas7efbhjmasdc';
        $this->certificatePassword = '8342rzhkSDHFBN84FHnaksmc01psDC341dx';
        $this->certAuthName = 'elastic-stack-http-ca.p12';
        $this->certAuthCert = 'elastic-stack-http-ca.crt';
        $this->certAuthPem = 'elastic-stack-http-ca.crt.pem';
        $this->elasticConfigLocation = '/etc/elasticsearch/elasticsearch.yml';
        $this->kibanaConfigLocation = '/etc/kibana/kibana.yml';
        $this->apmConfigLocation = '/etc/apm-server/apm-server.yml';

        $this->trustedCertificatesLocation = '/usr/local/share/ca-certificates/elasticsearch';
        // $this->trustedCertificatesLocation = '/usr/local/share/ca-certificates';

        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');
        $nodes = Digitalocean::getDroplets()
            ->filter(fn ($n) => $n['name'] == $this->argument('hostname'))
            ->filter(fn ($n) => is_numeric(substr($n['name'], -2, 2)))
            ->values()
        ;

        $masterNode = $nodes->first();

        // dd($nodes);

        // $this->generateCertificateAutority($masterNode); // on one node then copy certificate all others

        // $this->generateCertificates($masterNode, $nodes);

        // $this->secureHttpTraffic($nodes);

        // $this->setupKibanaTrust(array_filter($nodes, fn ($node) => str_contains($node['name'], '-kibana')));
        // $this->setupApmTrust(array_filter($nodes, fn ($node) => str_contains($node['name'], '-apm')));
        // $nodes = array_filter($nodes, fn ($node) => str_contains($node['name'], '-kibana'));
        $this->setupClientTrust($nodes);

        return;
        $clients = [
            [
                'name' => 'batzo-staging',
                'public_ip' => '178.128.198.122',
                'user' => 'forge',
                'password' => 'lbludmH5tbRK5OxN4bme',
            ], [
                'name' => 'batzo-web-3',
                'public_ip' => '167.71.34.61',
                'user' => 'forge',
                'password' => 's3yUqhovFxiTVCzhvlGe',
            ], [
                'name' => 'batzo-web-2',
                'public_ip' => '134.122.72.110',
                'user' => 'forge',
                'password' => 'ThK6RRxANBrliq4mts3j',
            ], [
                'name' => 'batzo-crawler-2',
                'public_ip' => '138.197.183.168',
                'user' => 'forge',
                'password' => '1AFpLsbwTqqbcqqyE2uu',
            ], [
                'name' => 'batzo-crawler-1',
                'public_ip' => '64.227.125.125',
                'user' => 'forge',
                'password' => '96TE4b348atywtSJfhnn',
            ], [
                'name' => 'batzo-web-ch',
                'public_ip' => '167.71.52.229',
                'user' => 'forge',
                'password' => '1jUnefPq9sbXSbaiWqF8',
            ], [
                'name' => 'batzo-worker-1',
                'public_ip' => '159.89.108.24',
                'user' => 'forge',
                'password' => 'CbNMGP1fLgqlcK8eb7Q5',
            ], [
                'name' => 'batzo-websockets',
                'public_ip' => '167.172.99.165',
                'user' => 'forge',
                'password' => 'uMC37YyEjtZr5OUioupj',
            ], [
                'name' => 'batzo-worker-2',
                'public_ip' => '206.81.24.217',
                'user' => 'forge',
                'password' => '0oD8LUTTKwyDz1Buxy3d',
            ],
        ];

        $this->setupClientTrust($clients);
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

    public function setupKibanaTrust($nodes)
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
                        "mv /tmp/{$this->certAuthPem} /etc/kibana/{$this->certAuthPem}",
                        "sudo chown kibana /etc/kibana/{$this->certAuthPem}",
                    ],
                    array_map(
                        fn ($setting) => "grep -qxF '{$setting}' {$this->kibanaConfigLocation} || echo '{$setting}' >> {$this->kibanaConfigLocation}",
                        [
                            "elasticsearch.ssl.certificateAuthorities: [\"{$this->certAuthPem}\"]",
                        ]
                    ),
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
