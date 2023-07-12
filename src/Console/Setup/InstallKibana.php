<?php

namespace Elastico\Console\Setup;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallKibana extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:kibana:install 
                                {ip : The SSH IP Adress} 
                                {--user=root : The SSH User} 
                                {--root-password= : The password for the RootUser} 
                                {--private-key= : The path to the SSH Private Key } 
                                {--port=22 : The SSH Port }
                                {--cluster= : The Elasticsearch cluster name}
                                {--host=_site_ : The Host where Elasticsearch is available}
                                {--seed-hosts=* : The Hosts used for discovery}
                                {--initial-masters=* : The master-eligible nodes}
                                {--enrollment-token= : The enrollment token to join an existing cluster}
                                ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Elasticsearch on a server';

    protected Ssh $ssh;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->createSSH(
            ip: $this->argument('ip'),
            user: $this->option('user'),
            port: $this->option('port'),
            privateKey: $this->option('private-key') // '/Users/gchaumont/.ssh/id_ed25519'
        );

        $this->installKibana();

        $this->configureKibana(
            host: $this->option('host'),
        );

        // return;
        $this->joinCluster(token: $this->option('enrollment-token'));

        $this->startKibana();
    }

    public function copyCertificate()
    {
        $this->ssh->download('/etc/elasticsearch/certs/http_ca.crt', __DIR__ . '/elastic-http-certificate.crt');
    }

    public function createSSH(
        string $user,
        string $ip,
        int $port,
        null|string $privateKey = null
    ): void {
        $this->ssh = Ssh::create(
            user: $user,
            host: $ip,
            port: $port
        )
            ->disableStrictHostKeyChecking()
            ->onOutput($this->handleOutput());

        if ($privateKey) {
            $this->ssh->usePrivateKey($privateKey);
        }

        if ($pw = $this->option('root-password')) {
            $this->ssh->execute("printf \"{$pw}\n\" | sudo -S su -");
        }
    }

    public function handleOutput(): \Closure
    {
        return function ($type, $line) {
            match ($type) {
                'err' => $this->warn($line),
                'out' => $this->line($line),
            };
        };
    }

    public function joinCluster(null|string $token): void
    {
        if (!is_null($token)) {
            $this->info('Preparing node to join existing cluster');

            // If this node should join an existing cluster, you can reconfigure this with
            // $this->ssh->execute("yes | /usr/share/elasticsearch/bin/elasticsearch-reconfigure-node --enrollment-token {$token}");
            $this->ssh->execute("yes | /usr/share/kibana/bin/kibana-setup --enrollment-token {$token}");
        }
    }

    public function installKibana(): void
    {
        $this->ssh->execute([
            'echo ">> Updating apt"',
            'apt update',

            'echo ">> Installing Elastic GPG Key"',
            'wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg
                ',

            'echo ">> Install apt-transport-https"',
            'sudo apt-get install apt-transport-https',
            'echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list',

            'echo ">> Installing Elasticsearch"',
            'sudo apt-get update && sudo apt-get install kibana',
            'echo ">> Kibana Installed"',
        ]);
    }

    public function startKibana(): void
    {
        $this->ssh->execute([
            'echo ">> Scheduling Elasticsearch"',
            '/bin/systemctl daemon-reload',
            '/bin/systemctl enable kibana.service',

            'echo ">> Starting Kibana"',
            'systemctl restart kibana.service',
        ]);
    }

    public function configureKibana(string $host): void
    {
        $temp_file = tmpfile();

        $temp_file_name = stream_get_meta_data($temp_file)['uri'];

        $this->ssh->download('/etc/kibana/kibana.yml', $temp_file_name);

        $contents = file_get_contents($temp_file_name);

        fclose($temp_file);

        $temp_file = tmpfile();

        $temp_file_name = stream_get_meta_data($temp_file)['uri'];

        $config = [
            '#server.host: "localhost"' => "server.host: \"{$host}\"",
            // '#network.host: 192.168.0.1' => "network.host: {$host}",
        ];

        // if ($seed_hosts) {
        //     $config['#discovery.seed_hosts: ["host1", "host2"]'] = 'discovery.seed_hosts: ['.implode(',', $seed_hosts).']';
        // }
        // if ($master_nodes) {
        //     $config['#cluster.initial_master_nodes: ["node-1", "node-2"]'] = 'cluster.initial_master_nodes: ['.implode(',', $master_nodes).']';
        // }

        foreach ($config as $key => $value) {
            $contents = str_replace($key, $value, $contents);
        }

        fwrite($temp_file, $contents);

        $this->ssh->upload($temp_file_name, '/etc/kibana/kibana.yml');

        fclose($temp_file);
    }
}
