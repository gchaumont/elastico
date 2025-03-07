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

    public function testConnection(string $ip): void
    {
        $this->ssh->execute("curl --cacert /etc/elasticsearch/certs/http_ca.crt -u elastic:{$this->elastic_password} https://$ip:9200/_cluster/health ");
    }
}
