<?php

namespace Elastico\Console;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:elasticsearch:install {ip} {--config-path=} {--user=root} {--password=} {--key=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Elasticsearch on a server ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

        $nodes = array_filter($nodes, fn ($n) => $n['name'] == $this->argument('hostname'));

        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            $process = $sshClient
                ->execute([
                    $this->installElasticsearch(),
                ])
            ;

            $this->pushConfiguration($sshClient, $node['private_ip']);

            $process = $sshClient
                ->execute([
                    $this->startElasticsearch(),
                ])
            ;

            $this->info($process->isSuccessful() ? 'success' : 'failed');
            $this->info($process->getOutput() ?: 'no output');
        }
    }

    public function installElasticsearch(): string
    {
        return implode("\n", [
            'echo ">> Updating apt"',
            'apt update',

            'echo ">> Installing Elastic GPG Key"',
            'wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg',

            'echo ">> Install apt-transport-https"',
            'sudo apt-get install apt-transport-https',
            'echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list',

            'echo ">> Installing Elastic"',
            'sudo apt-get update && sudo apt-get install elasticsearch',
            'echo ">> Elastic Search Installed"',

            // 'echo ">> Adding deb package"',
            // 'echo "deb https://artifacts.elastic.co/packages/7.x/apt stable main" | sudo tee -a /etc/apt/sources.list.d/elastic-7.x.list',

            // 'echo ">> Installing Java and Elastic Search"',
            // 'apt -y install default-jre elasticsearch',

            'echo ">> Scheduling Elasticsearch"',
            '/bin/systemctl daemon-reload',
            '/bin/systemctl enable elasticsearch.service',
        ]);
    }

    // public function installS3(): string
    // {
    //     return implode("\n", [
    //         '/usr/share/elasticsearch/bin/elasticsearch-plugin install repository-s3 -b',
    //         //'/usr/share/elasticsearch/bin/elasticsearch-keystore remove s3.client.default.access_key',
    //         //'/usr/share/elasticsearch/bin/elasticsearch-keystore remove s3.client.default.secret_key',
    //         'AWS_ACCESS_KEY_ID=P73OHM6HXT2OFJ6CLQCE',
    //         'echo $AWS_ACCESS_KEY_ID | /usr/share/elasticsearch/bin/elasticsearch-keystore add --stdin s3.client.default.access_key',
    //         'AWS_SECRET_ACCESS_KEY=EVmND92kuuf/abjl98EPBVldxhhfcgGv8lGASkXSZhg',
    //         'echo $AWS_SECRET_ACCESS_KEY | /usr/share/elasticsearch/bin/elasticsearch-keystore add --stdin s3.client.default.secret_key',
    //         // POST _nodes/reload_secure_settings
    //     ]);
    // }

    public function startElasticsearch(): string
    {
        return implode("\n", [
            'systemctl restart elasticsearch.service',
        ]);
    }

    public function pushConfiguration($sshClient, $nodeIp)
    {
        $tmpfile = tmpfile();

        $contents = file_get_contents(storage_path('elastic/config/elasticsearch.yml'));

        $contents = str_replace('{{NODE_LOCAL_IP}}', $nodeIp, $contents);

        fwrite($tmpfile, $contents);

        $sshClient->upload(stream_get_meta_data($tmpfile)['uri'], '/etc/elasticsearch/elasticsearch.yml');

        fclose($tmpfile);
    }
}
