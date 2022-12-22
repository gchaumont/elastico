<?php

namespace Elastico\Console\Indices;

use App\Support\Elasticsearch\Client\ElasticsearchClient;
use Elastico\Exceptions\IndexNotFoundException;
use Illuminate\Console\Command;

class DeleteIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:index:delete {index} {--raw}';

    protected ElasticsearchClient $client;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete an elasticsearch index';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('raw')) {
            $class = $this->argument('index');
            $indexName = (new $class())->getTable();
        } else {
            $indexName = $this->argument('index');
        }
        if (empty($indexName)) {
            return;
        }

        try {
            $r = (new $class())->getConnection()->getClient()->indices()->delete(['index' => $indexName]);
        } catch (IndexNotFoundException) {
            dump('Index not found');
        }
    }
}
