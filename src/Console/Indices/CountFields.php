<?php

namespace Elastico\Console\Indices;

use Illuminate\Console\Command;

class CountFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:index:countFields {index}  {--connection= : Elasticsearch connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the count of Fields in an index';

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

        $count = collect($class::indexProperties())
            ->map(fn ($prop) => $prop->propCount())
            ->sum()
        ;

        return $this->info("{$count} Fields in Index");
    }
}
