<?php

namespace Elastico\Console\DataStreams;

use stdClass;
use Illuminate\Console\Command;
use Elastico\Eloquent\DataStream;
use Elastico\Exceptions\IndexNotFoundException;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class DeleteDataStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:datastream:delete {index}
                                {--connection= : Elasticsearch connection}
                                {--force : Skip confirmation}
        ';

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

        if ($this->option('force') || $this->confirm('Are you sure you want to delete this DataStream?')) {

            try {

                $model->getConnection()->getClient()->indices()->deleteDataStream([
                    'name' => $model->getTable()
                ]);
            } catch (IndexNotFoundException) {
                $this->error('Index not found');
            } catch (ClientResponseException $e) {
                if (str_contains($e->getMessage(), 'index_not_found_exception') || str_contains($e->getMessage(), 'resource_not_found_exception')) {
                    $this->error('Index not found');
                } else {

                    throw $e;
                }
            }

            return $this->info("{$class} DataStream deleted");
        }
    }
}
