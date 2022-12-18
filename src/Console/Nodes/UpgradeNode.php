<?php

namespace Elastico\Console\Nodes;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class UpgradeNode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade Node to new elasticsearch version';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nodes = [
            '68.183.77.204:9200',
            '167.71.52.155:9200',
            '188.166.160.85:9200',
            '139.59.211.91',
            '68.183.215.173',
            '159.89.109.164',
            '167.172.187.71',
            '161.35.216.203',
            '161.35.222.102',
            '165.227.149.122',
            '167.172.184.42',
        ];

        foreach ($nodes as $nodeIp) {
            $process = Ssh::create('root', $nodeIp)
                ->disableStrictHostKeyChecking()
                ->usePrivateKey('/Users/gchaumont/.ssh/id_ed25519')
                ->onOutput(fn ($type, $line) => $this->info($type.' '.$line))
                ->execute([
                    $this->listShards(),
                    // $this->disableShardAllocation(),
                    // $this->flush(),
                    // $this->stopElastic(),
                    // $this->upgrade(),
                    // $this->upgradePlugins(),
                    // $this->enableShardAllocation(),
                    // $this->appendXpackSecurity(),
                    // $this->restart(),
                ])
            ;
            $this->info($process->isSuccessful() ? 'success' : 'failed');
            $this->info($process->getOutput() ?: 'no output');

            return;
        }
    }

    public function listShards()
    {
        return 'curl -X GET "10.135.131.201:9200/_cat/shards?pretty"';
    }

    public function disableShardAllocation(): string
    {
        return 'curl -X PUT "localhost:9200/_cluster/settings?pretty" -H \'Content-Type: application/json\' -d\' {
            "persistent": {
                "cluster.routing.allocation.enable": "primaries"
            }
        }
        \'';
    }

    public function flush(): string
    {
        return 'curl -X POST "localhost:9200/_flush?pretty"';
    }

    public function stopElastic(): string
    {
        return 'systemctl stop elasticsearch.service';
    }

    public function upgrade()
    {
        return 'apt-get update && sudo apt-get install elasticsearch';
    }

    public function upgradePlugins(): string
    {
        return implode("\n", [
            '/usr/share/elasticsearch/bin/elasticsearch-plugin remove repository-s3',
            // 'sleep 1',
            '/usr/share/elasticsearch/bin/elasticsearch-plugin install repository-s3 -b',
        ]);
    }

    public function restart(): string
    {
        return 'systemctl start elasticsearch.service';
    }

    public function enableShardAllocation(): string
    {
        return 'curl -X PUT "localhost:9200/_cluster/settings?pretty" -H \'Content-Type: application/json\' -d\' {
            "persistent": {
                "cluster.routing.allocation.enable": null
            }
        }
        \'';
    }

    public function appendXpackSecurity(): string
    {
        return "sed -i '$ d' /etc/elasticsearch/elasticsearch.yml"; // remove last line

        return 'echo "xpack.security.enabled: true" >> /etc/elasticsearch/elasticsearch.yml'; // append to file
    }
}
