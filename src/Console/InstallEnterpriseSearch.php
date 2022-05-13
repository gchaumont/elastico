<?php

namespace Elastico\Console;

use App\Support\Digitalocean\Digitalocean;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class InstallEnterpriseSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:ent-search:install {hostname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Enterprise Search on a server ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->enterpriseSearchPassword = '23kiruhkasdfnkiHRUKBMNSDF';

        //    ENT_SEARCH_DEFAULT_PASSWORD=23kiruhkasdfnkiHRUKBMNSDF bin/enterprise-search

        $nodes = Digitalocean::getDroplets(tag: 'elasticsearch');

        $nodes = array_filter($nodes, fn ($n) => $n['name'] == $this->argument('hostname'));

        foreach ($nodes as $node) {
            $sshClient = Ssh::create('root', $node['public_ip'])
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
            ;

            // $process = $sshClient
            //     ->execute([
            //         $this->installEnterpriseSearch(),
            //     ])
            // ;

            // $this->pushConfiguration($sshClient, $node['private_ip']);

            $process = $sshClient
                ->execute(
                    $this->startEnterpriseSearch(),
                )
            ;

            $this->info($process->isSuccessful() ? 'success' : 'failed');
            $this->info($process->getOutput() ?: 'no output');
        }

        $this->call('system:elastic:ca:update', ['hostname' => $this->argument('hostname')]);
    }

    public function startEnterpriseSearch()
    {
        return [
            'cd enterprise-search-7.16.2',
            "ENT_SEARCH_DEFAULT_PASSWORD={$this->enterpriseSearchPassword} bin/enterprise-search",
        ];
    }

    public function installEnterpriseSearch(): string
    {
        return implode("\n", [
            'echo ">> Updating apt"',
            'apt update',

            'echo ">> Installing Java "',
            'apt -y install default-jre',

            'echo ">> Java Installed"',

            'curl -L -O https://artifacts.elastic.co/downloads/enterprise-search/enterprise-search-7.16.2.tar.gz',
            'tar xzvf enterprise-search-7.16.2.tar.gz',
            'cd enterprise-search-7.16.2',

            // 'echo ">> Elastic Search Installed"',

            // 'echo ">> Scheduling Elasticsearch"',
            // '/bin/systemctl daemon-reload',
            // '/bin/systemctl enable elasticsearch.service',
        ]);
    }

    public function pushConfiguration($sshClient, $nodeIp)
    {
        $tmpfile = tmpfile();

        $contents = file_get_contents(storage_path('elastic/config/enterprise-search.yml'));

        //$contents = str_replace('{{NODE_LOCAL_IP}}', $nodeIp, $contents);

        fwrite($tmpfile, $contents);

        $sshClient->upload(stream_get_meta_data($tmpfile)['uri'], '~/enterprise-search-7.16.2/config/enterprise-search.yml');

        fclose($tmpfile);
    }
}
