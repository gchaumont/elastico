<?php

namespace Elastico\Console\Setup;

use Exception;
use Throwable;
use Spatie\Ssh\Ssh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:elasticsearch:install 
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
                                {--s3-endpoint= : The S3 endpoint}
                                ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Elasticsearch on a server';

    protected Ssh $ssh;

    private string $elastic_password;


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->validateInput();

            $this->createSSH(
                ip: $this->argument('ip'),
                user: $this->option('user'),
                port: $this->option('port'),
                privateKey: $this->option('private-key'), // '/Users/gchaumont/.ssh/id_ed25519'
                password: $this->option('root-password'),
            );

            $this->installElasticsearch();

            $this->configureNode(
                cluster: $this->option('cluster') ?? $this->ask('What is the Cluster name?'),
                host: $this->option('host'),
                seed_hosts: $this->option('seed-hosts') ?? [],
                master_nodes: $this->option('initial-masters') ?? [],
                s3_endpoint: $this->option('s3-endpoint'),
            );

            $this->joinCluster(token: $this->option('enrollment-token'));

            $this->startElasticsearch();

            // $this->testConnection("localhost");


            $path = $this->copyCertificate();

            $this->info('Elastic password: ' . ($this->elastic_password ?? null));
            $this->info('Certificate File: ' . $path);
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
    }

    public function validateInput(): void
    {
        if (!$this->argument('ip')) {
            throw new Exception('The SSH IP address is required.');
        }

        // Add any other validation as per your needs
    }

    public function testConnection(string $ip): void
    {
        $this->ssh->execute("curl --cacert /etc/elasticsearch/certs/http_ca.crt -u elastic:{$this->elastic_password} https://$ip:9200/_cluster/health ");
    }

    public function copyCertificate()
    {
        if (!file_exists(storage_path('certificates/elastic'))) {
            mkdir(storage_path('certificates/elastic'));
        }

        $path = storage_path('certificates/elastic/elastic-http-certificate.crt');

        if (!file_exists($path)) {

            $this->ssh->download('/etc/elasticsearch/certs/http_ca.crt', $path);

            return $path;
        }
    }

    public function createSSH(
        string $user,
        string $ip,
        int $port,
        null|string $privateKey = null,
        null|string $password = null,
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

        if ($password) {
            $this->ssh->execute("printf \"{$password}\n\" | sudo -S su -");
        }
    }

    public function handleOutput(): \Closure
    {
        return function ($type, $line) {
            $this->handlePasswordCapture($line);
            $this->handleConsoleOutput($type, $line);
        };
    }

    private function handlePasswordCapture($line): void
    {
        $match = preg_match('#The generated password for the elastic built-in superuser is : (\S*)#', $line, $matches);
        if (1 === $match) {
            $this->elastic_password = $matches[1];
        }
    }

    private function handleConsoleOutput($type, $line): void
    {
        match ($type) {
            'err' => $this->warn($line),
            'out' => $this->line($line),
        };
    }


    public function joinCluster(null|string $token): void
    {
        if (!is_null($token)) {
            $this->info('Preparing node to join existing cluster');

            // If this node should join an existing cluster, you can reconfigure this with
            $this->ssh->execute("yes | /usr/share/elasticsearch/bin/elasticsearch-reconfigure-node --enrollment-token " . escapeshellarg($token));
        }
    }

    public function installElasticsearch(): void
    {
        $this->ssh->execute([
            'echo ">> Updating apt"',
            'apt update',

            'echo ">> Installing Elastic GPG Key"',
            'wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --yes --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg',

            'echo ">> Install apt-transport-https"',
            'sudo apt-get install apt-transport-https',
            'echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list',

            'echo ">> Installing Elasticsearch"',
            'sudo apt-get update && sudo apt-get install elasticsearch',
            'echo ">> Elastic Search Installed"',
        ]);
    }

    public function startElasticsearch(): void
    {
        $this->ssh->execute([
            'echo ">> Scheduling Elasticsearch"',
            '/bin/systemctl daemon-reload',
            '/bin/systemctl enable elasticsearch.service',

            'echo ">> Starting Elasticsearch"',
            'systemctl restart elasticsearch.service',
        ]);
    }

    public function configureNode(string $cluster, string $host, array $seed_hosts, array $master_nodes = [], string $s3_endpoint = null): void
    {
        $temp_file = tmpfile();

        $temp_file_name = stream_get_meta_data($temp_file)['uri'];

        $this->ssh->download('/etc/elasticsearch/elasticsearch.yml', $temp_file_name);

        $contents = file_get_contents($temp_file_name);

        fclose($temp_file);

        $temp_file = tmpfile();

        $temp_file_name = stream_get_meta_data($temp_file)['uri'];

        $config = [
            '#cluster.name: my-application' => "cluster.name: {$cluster}",
            // '#network.host: 192.168.0.1' => "network.host: {$host}",
            '#network.host: 192.168.0.1' => "network.host: {$host}",
        ];

        foreach ($config as $key => $value) {
            $contents = str_replace($key, $value, $contents);
        }


        if ($seed_hosts) {
            // delete line that contains discovery.seed_hosts
            $contents = preg_replace('/discovery.seed_hosts: \[.*\]/', '', $contents);

            $contents .= "\ndiscovery.seed_hosts: [" . implode(',', $seed_hosts) . "]";
        }
        if ($master_nodes) {
            $contents = preg_replace('/cluster.initial_master_nodes: \[.*\]/', '', $contents);

            $contents .= "\ncluster.initial_master_nodes: [" . implode(',', $master_nodes) . "]";
        }

        # if content does not contain config : http.host: 0.0.0.0 add it 
        if (!str_contains($contents, 'http.host:')) {
            $contents .= "\nhttp.host: 0.0.0.0";
        }

        # same with s3.client.spaces.endpoint: fra1.digitaloceanspaces.com
        if ($s3_endpoint && !str_contains($contents, 's3.client.spaces.endpoint:')) {
            $contents .= "\ns3.client.spaces.endpoint: $s3_endpoint";
        }

        fwrite($temp_file, $contents);

        $this->ssh->upload($temp_file_name, '/etc/elasticsearch/elasticsearch.yml');

        fclose($temp_file);
    }
}
