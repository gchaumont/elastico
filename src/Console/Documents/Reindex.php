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
    protected $signature = 'elastic:docs:reindex {source} {destination} 
            {--connection= : Elasticsearch connection}
            {--_source=* : Source fields to include in the reindex}
            {--max= : Maximum number of documents to reindex}
            ';

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

        $params = [

            'body' => [
                'source' => [
                    'index' => $this->argument('source'),
                    '_source' => $this->option('_source') ?? '*',
                ],
                'dest' => [
                    'index' => $this->argument('destination'),
                ],
            ]
        ];

        if ($this->option('max')) {
            $params['max_docs'] = $this->option('max');
        }

        $connection->getClient()->reindex($params);

        return $this->info('Documents Reindexed');
    }
}
