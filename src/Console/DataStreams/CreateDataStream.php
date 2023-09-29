<?php

namespace Elastico\Console\DataStreams;

use Elastico\Eloquent\DataStream;
use Illuminate\Console\Command;
use stdClass;

class CreateDataStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:datastream:create {index}
                                {--connection= : Elasticsearch connection}
                                {--fresh= : Delete index if it exists}
                                ';

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

        $model = new $class;

        if (!$model instanceof DataStream) {
            return $this->error("{$class} is not a DataStream");
        }

        if ($this->option('fresh')) {
            $this->call('elastic:datastream:delete',  [
                'index' => $model::class,
                '--connection' => $this->option('connection'),
                '--force' => true,
            ]);
        }


        $policy = $model->getILMPolicy();

        $model->getConnection()->getClient()->ilm()->putLifecycle([
            'policy' => $policy->getName(),
            'body' => $policy->toArray()
        ]);

        $config = $model::getIndexConfig()->toArray();

        $template = [];
        $template['body']['index_patterns'] = [$model->getTable() . '*'];
        $template['body']['data_stream'] = new \stdClass();
        $template['body']['priority'] = '300'; // higher than 200 avoid collision with builtin templates
        $template['body']['template'] = $config['body'];

        if (($template['body']['template']['settings'] ?? null) == new stdClass) {
            unset($template['body']['template']['settings']);
        }
        $template['body']['template']['settings']['index']['lifecycle']['name'] = $policy->getName();

        $model->getConnection()->getClient()->indices()->putIndexTemplate([
            'name' => $model->getTable(),
            'body' => $template['body'],
        ]);

        $model->getConnection()->getClient()->indices()->createDataStream([
            'name' => $model->getTable()
        ]);

        return $this->info("{$class} DataStream Created");
    }
}
