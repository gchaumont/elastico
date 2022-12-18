<?php

namespace Elastico\Console\Indices;

use Illuminate\Console\Command;

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

        $model = new $class();

        if ($this->option('connection')) {
            $model->setConnection($this->option('connection'));
        }

        $config = $class::getIndexConfiguration();

        $model->getConnection()->getClient()->indices()->create($config);

        return $this->info("{$class} Index Created");
    }
}
