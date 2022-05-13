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

        $class::getConnection()->getClient()->indices()->create($class::getIndexConfiguration());

        return $this->info("{$class} Index Created");
    }
}
