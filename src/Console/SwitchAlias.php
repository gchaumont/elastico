<?php

namespace Elastico\Console;

use App\Models\Affiliates\Records\CompressedAdvertisementRecord;
use App\Support\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class SwitchAlias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:alias:switch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch a current alias to a new index';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Create INDEX TEMPLATE batzo.affiliates.advertisements.records.0*
        // Create ALIAS batzo.affiliates.advertisements.records:write -> batzo.affiliates.advertisements.records-01

        // IF INDEX batzo.affiliates.advertisements.records.0last > 500'000'000 records
        //      Update ALIAS batzo.affiliates.advertisements.records:write -> batzo.affiliates.advertisements.records.0next

        $elastic = resolve(Elasticsearch::class);

        $response = $elastic->indices()->stats(['index' => '_all']);
        $indices = array_filter($response['indices'], fn ($indexName) => str_starts_with($indexName, 'batzo.affiliates.advertisements.records.0'), ARRAY_FILTER_USE_KEY);

        ksort($indices);

        $alias = 'batzo.affiliates.advertisements.records-write';
        // dd($elastic->indices()->getTemplate('writable_affiliate_advertisement_records'));
        if (true || empty($indices)) {
            $templateConfig = CompressedAdvertisementRecord::getTemplateConfiguration();

            $templateConfig['body']['index_patterns'] = ['batzo.affiliates.advertisements.records.*'];

            // if (!$elastic->indices()->templateExists('writable_affiliate_advertisement_records')) {
            //     $r = $elastic->indices()->createTemplate(
            //         $templateConfig,
            //         'writable_affiliate_advertisement_records'
            //     );
            // }
            // $elastic->indices()->create(['index' => 'batzo.affiliates.advertisements.records.000001', 'body' => '']);

            dd($elastic->indices()->getAliases());
            $r = $elastic->indices()->updateAliases([[
                'add' => [
                    'index' => 'batzo.affiliates.advertisements.records.000001',
                    'alias' => $alias,
                ],
            ]]);
        } else {
            $latestIndex = end($indices);
            $latestIndexName = array_key_last($indices);
            $latestIndexNameParts = explode('.', $latestIndexName);
            $latestIndexNumber = array_shift($latestIndexNameParts);

            if ($latestIndex['primaries']['docs']['count'] > 500 * 1000 * 1000) {
                $nextIndexName = implode('.', $latestIndexNameParts).'.'.$latestIndexNumber++;

                $elastic->indices()->updateAliases([
                    [
                        'remove' => [
                            'index' => $latestIndexName,
                            'alias' => $alias,
                        ],
                    ],
                    [
                        'add' => [
                            'index' => $nextIndexName,
                            'alias' => $alias,
                        ],
                    ],
                ]);
            }
        }

        $aliasExists = $elastic->indices()->aliasExists($alias);

        dd($aliasExists);

        $currentAliases = $elastic->indices()->getAlias($alias);

        $actions = [];

        foreach ($currentAliases as $index => $al) {
            $actions[] = [
                'remove' => [
                    'index' => $index,
                    'alias' => $alias,
                ],
            ];
        }

        $elastic->indices()->updateAliases([...$actions, [
            'add' => [
                'index' => $this->argument('index'),
                'alias' => $alias,
            ],
        ]]);

        $response = $elastic->indices()->getAlias($alias);

        return $this->info(json_encode($response));
    }
}
