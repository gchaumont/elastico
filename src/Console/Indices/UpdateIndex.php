<?php

namespace Elastico\Console\Indices;

use App\Support\Elasticsearch\Client\ElasticsearchClient;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class UpdateIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:index:update {index} {--connection= : Elasticsearch connection}';

    protected ElasticsearchClient $client;

    protected Filesystem $files;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update an Elasticsearch index';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $class = $this->argument('index');

        $config = $class::getIndexConfiguration();

        $model = new $class();

        if ($this->option('connection')) {
            $model->setConnection($this->option('connection'));
        }
        // dd($config['body']['mappings']);
        $model->getConnection()->getClient()->indices()->putMapping([
            'index' => $config['index'],
            'body' => $config['body']['mappings'],
        ]);

        $this->info("{$class} Index Updated");
    }
}
