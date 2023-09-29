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

        /** @var Model $model */
        $model = new $class();

        if ($model instanceof DataStream) {
            return $this->call('elastic:datastream:update',  [
                'index' => $model::class,
                '--connection' => $this->option('connection'),
                '--fresh' => $this->option('fresh'),
            ]);
        }

        if ($this->option('connection')) {
            $model->setConnection($this->option('connection'));
        }

        $config = $model::getIndexConfig();

        // $model->getConnection()->getClient()->indices()->putSettings([
        //     'index' => $config['index'],
        //     'body' => $config['body']['settings'],
        // ]);

        $model->getConnection()->getClient()->indices()->putMapping([
            'index' => $config['index'],
            'body' => $config['body']['mappings'],
        ]);

        return $this->info("{$class} Index Updated");
    }
}
