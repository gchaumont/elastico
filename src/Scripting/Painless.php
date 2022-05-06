<?php

namespace Gchaumont\Scripting;

use Elasticsearch\ClientBuilder;

/**
 * Stored Elasticsearch Scripts.
 */
class Painless
{
    // if (!ctx._source.containsKey("history") || ctx._source.price != params.price) {
    //     if(!ctx._source.containsKey("history")){ctx._source.history = []}
    //     ctx._source.history.add([
    //             "date" : params.today,
    //             "price" : params.price
    //         ]);
    //     update = true;
    // }

    public static $scripts = [
        'upsert-offer' => '
                boolean update = false;

                for (field in ["url", "price", "shop_id", "advertiser_id", "feed_id", "currency", "country", "availability", "condition"]) {
                if(ctx._source[field] != params[field]){
                        update = true;
                        ctx._source[field] = params[field];
                        }
                }

                if (update == false) {
                    ctx.op = "none";
                }
        ',
    ];

    public static function indexScripts()
    {
        $client = ClientBuilder::create()
            ->setHosts(config('scout.elasticsearch.hosts'))
            ->build()
        ;

        $response = $client->cluster()->state(['metric' => 'metadata']);
        if (isset($response['metadata']['stored_scripts'])) {
            $currentScripts = array_keys($response['metadata']['stored_scripts']);
            foreach ($currentScripts as $script) {
                $response = $client->deleteScript(['id' => $script]);
            }
        }

        foreach (static::$scripts as $id => $body) {
            $response = $client->putScript(
                [
                    'id' => $id,
                    'body' => [
                        'script' => [
                            'lang' => 'painless',
                            'source' => preg_replace('/\s+/', ' ', $body), ],
                    ],
                ]
            );
        }

        return $response['acknowledged'];
    }
}
