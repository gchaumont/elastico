<?php

namespace Elastico\Console\DataStreams;

use Elastico\Eloquent\DataStream;
use Illuminate\Console\Command;
use stdClass;

class DeleteDataStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:datastream:delete {index}';

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

        $model = new $class;

        if (!$model instanceof DataStream) {
            return $this->error("{$class} is not a DataStream");
        }


        $model->getConnection()->getClient()->indices()->deleteDataStream([
            'name' => $model->getTable()
        ]);

        return $this->info("{$class} DataStream Created");
    }
}
