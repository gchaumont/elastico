<?php

namespace Elastico\Console\Indices;

use Elastico\Eloquent\Model;
use Illuminate\Console\Command;
use Elastico\Eloquent\DataStream;

class CreateIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:index:create {index}  {--connection= : Elasticsearch connection}';

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

        /** @var Model $model */
        $model = new $class();

        if ($model instanceof DataStream) {
            return $this->call('elastic:datastream:create',  ['index' => $model::class]);
        }

        if ($this->option('connection')) {
            $model->setConnection($this->option('connection'));
        }

        $config = $model::getIndexConfig();

        $model->getConnection()->getClient()->indices()->create($config->toArray());

        return $this->info("{$class} Index Created");
    }
}
