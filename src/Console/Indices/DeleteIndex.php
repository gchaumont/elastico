<?php

namespace Elastico\Console\Indices;

use Illuminate\Console\Command;
use Elastico\Eloquent\DataStream;
use Elastico\Exceptions\IndexNotFoundException;
use App\Support\Elasticsearch\Client\ElasticsearchClient;

class DeleteIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:index:delete {index : The index class or name} 
                                {--connection= : Elasticsearch connection}
                                {--force : Skip confirmation}
                                ';

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
        $class = $this->argument('index');

        if (class_exists($class)) {
            $indexName = (new $class())->getTable();

            if ((new $class) instanceof DataStream) {
                return $this->call('elastic:datastream:delete',  [
                    'index' => $class,
                    '--connection' => $this->option('connection'),
                    '--force' => $this->option('force'),
                ]);
            }
        } else {
            $indexName = $this->argument('index');
        }

        if ($this->option('force') || $this->confirm('Are you sure you want to delete this index?')) {

            try {
                $r = (new $class())->getConnection()->getClient()->indices()->delete(['index' => $indexName]);
            } catch (IndexNotFoundException) {
                $this->error('Index not found');
            }
        }
    }
}
