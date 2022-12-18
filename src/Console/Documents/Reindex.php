<?php

namespace Elastico\Console\Documents;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Reindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:docs:reindex {source} {destination} {--connection= : Elasticsearch connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex elasticsearch documents';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $connection = DB::connection($this->option('connection') ?? 'elastic');

        $connection->getClient()->reindex(['body' => [
            'source' => [
                'index' => $this->argument('source'),
            ],
            'dest' => [
                'index' => $this->argument('destination'),
            ],
        ]]);

        return $this->info('Documents Reindexed');
    }
}
