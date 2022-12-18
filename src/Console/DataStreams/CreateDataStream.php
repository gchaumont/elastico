<?php

namespace Elastico\Console\DataStreams;

use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class CreateDataStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:datastream:create {index}';

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

        $template = $class::getTemplateConfiguration();
        $template['body']['data_stream'] = new \stdClass();
        $template['body']['index_patterns'] = $class::INDEX_NAME.'*';
        $template['body']['priority'] = '300'; // higher than 200 avoid collision with builtin templates

        $template['body']['template']['settings']['index']['lifecycle']['name'] = $class::INDEX_LIFECYCLE;
        // Create with Alias
        // $template['body']['template']['settings']['index']['lifecycle']['rollover_alias'] = AdvertisementRecord::INDEX_NAME;

        $r = resolve(Elasticsearch::class)->indices()->createTemplate($template, $class::INDEX_NAME);
        $r = resolve(Elasticsearch::class)->indices()->createDataStream(['name' => $class::INDEX_NAME]);

        return $this->info("{$class} DataStream Created");
    }
}
