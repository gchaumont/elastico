<?php

namespace Elastico\Console\Setup;

use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

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
            user : $this->option('user'),
            port : $this->option('port'),
            privateKey : $this->option('private-key') // '/Users/gchaumont/.ssh/id_ed25519'
        );

        $this->installElasticsearch();

        $this->configureNode(
            cluster: $this->option('cluster') ?? $this->ask('What is the Cluster name?'),
            host: $this->option('host'),
            seed_hosts : $this->option('seed-hosts') ?? [],
            master_nodes : $this->option('initial-masters') ?? [],
        );

        // // return;
        $this->joinCluster(token: $this->option('enrollment-token'));

        $this->startElasticsearch();

        $this->testConnection();
        $this->copyCertificate();

        $this->info('Elastic password: '.($this->elastic_password ?? null));
        $this->info('Certificate File: '.__DIR__.'/elastic-http-certificate.crt');
    }

    public function testConnection(): void
    {
        // $client = ClientBuilder::create()
        //     ->setHosts([$this->argument('ip')])
        //     ->setBasicAuthentication('elastic', ($this->elastic_password ?? 'I9NOUIdPTG2*E3yGrMmK'))
        //     ->setHttpClient(new GuzzleClient())
        //     ->setCABundle(__DIR__.'/elastic-http-certificate.crt')
        //     ->build()
        // ;

        $this->ssh->execute("curl --cacert /etc/elasticsearch/certs/http_ca.crt -u elastic:{$this->elastic_password} https://localhost:9200/_cluster/health ");

        // dump($client->cluster()->health());
    }

    public function copyCertificate()
    {
        if (!file_exists(storage_path('elastic'))) {
            mkdir(storage_path('elastic'));
        }

        $this->ssh->download('/etc/elasticsearch/certs/http_ca.crt', storage_path('elastic/elastic-http-certificate.crt'));
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
            ->onOutput($this->handleOutput())
        ;

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
            $match = preg_match('#The generated password for the elastic built-in superuser is : (\S*)#', $line, $matches);
            if (1 === $match) {
                $this->elastic_password = $matches[1];
            }
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
            $this->ssh->execute("yes | /usr/share/elasticsearch/bin/elasticsearch-reconfigure-node --enrollment-token {$token}");
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

            // 'echo ">> Adding deb package"',
            // 'echo "deb https://artifacts.elastic.co/packages/7.x/apt stable main" | sudo tee -a /etc/apt/sources.list.d/elastic-7.x.list',

            // 'echo ">> Installing Java and Elastic Search"',
            // 'apt -y install default-jre elasticsearch',
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

    public function configureNode(string $cluster, string $host, array $seed_hosts, array $master_nodes = []): void
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

        $this->ssh->upload($temp_file_name, '/etc/elasticsearch/elasticsearch.yml');

        fclose($temp_file);
    }
}
