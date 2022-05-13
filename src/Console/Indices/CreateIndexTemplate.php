<?php

namespace Elastico\Console\Indices;

use App\Support\Elasticsearch\Client\ElasticsearchClient;
use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CreateIndexTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:index:createTemplate {model}';

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
        $class = $this->argument('model');

        resolve(Elasticsearch::class)->indices()->createTemplate($class::getTemplateConfiguration(), strtolower(class_basename($class)));

        return $this->info("{$class} Index Template Created");
    }
}
