<?php

namespace Elastico\Console\Indices;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class CreateIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:index:create {index}';

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

        resolve(Elasticsearch::class)->indices()->create($class::getIndexConfiguration());

        return $this->info("{$class} Index Created");
    }
}
