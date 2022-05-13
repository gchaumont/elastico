<?php

namespace Elastico\Console\Indices;

use App\Support\Elasticsearch\Client\ElasticsearchClient;
use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class UpdateIndexSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:index:updatesettings {index}';

    protected ElasticsearchClient $client;

    protected Filesystem $files;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an elasticsearch index';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $class = $this->argument('index');

        $config = $class::getIndexConfiguration();

        unset($config['body']['settings']['analysis']);

        resolve(Elasticsearch::class)->indices()->putSettings([
            'index' => $config['index'],
            'body' => $config['body']['settings'],
        ]);

        $this->info("{$class} Index Settings Updated");
    }
}
