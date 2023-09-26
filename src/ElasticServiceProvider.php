<?php

namespace Elastico;

use Closure;
use Elastico\Query\Builder;
use Elastico\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Elastico\Query\Response\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as BaseCollection;
use Elastico\Eloquent\Builder as ElasticEloquentBuilder;

/**
 *  Elasticsearch ServiceProvider.
 */
class ElasticServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);

        $this->registerCommands();


        BaseCollection::macro('getBulk', function (iterable|callable $queries) {
            return $this
                ->flatMap(static function (mixed $model, string $model_key) use ($queries): BaseCollection {
                    $queries = $queries instanceof Closure ? $queries($model) : collect($queries)->map(fn ($query) => $query($model));

                    return collect($queries)
                        ->keyBy(fn ($query, $query_key): string => implode('::', [$model_key, $query_key]));
                })
                ->groupBy(static fn (Builder|ElasticEloquentBuilder|Relation $query): string => $query->getConnection()->getName(), preserveKeys: true)
                ->map(static fn (BaseCollection $queries, string $connection): array => DB::connection($connection)->query()->getMany($queries->all()))
                ->collapse()
                ->groupBy(static fn (BaseCollection $response, string $query_key): string => explode('::', $query_key, 2)[0], preserveKeys: true)
                ->map(static fn (BaseCollection $responses, string $model_id): BaseCollection => $responses->keyBy(fn (Collection $response, $response_key) => explode('::', $response_key, 2)[1]));
        });
    }

    public function register()
    {
        // Add Eloquent Database driver.
        $this->app->resolving('db', static function ($db) {
            $db->extend('elastic', static function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });
    }

    public function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CreateElasticsearchNode::class,
                Console\IndexPainless::class,
                Console\InstallElasticAgent::class,
                Console\Setup\InstallElasticsearch::class,
                Console\Setup\InstallKibana::class,
                Console\Setup\RemoveElasticsearch::class,
                Console\Setup\GenerateEnrollmentToken::class,
                Console\Setup\SetupS3Repository::class,
                Console\InstallEnterpriseSearch::class,
                Console\InstallFilebeat::class,
                Console\InstallFleet::class,
                Console\InstallMetricbeat::class,
                Console\InstallPacketbeat::class,
                Console\SwitchAlias::class,
                Console\UpgradeElasticsearch::class,

                Console\Backups\Backup::class,
                Console\Backups\CleanBackup::class,

                Console\Cluster\ClusterHealth::class,
                Console\Cluster\ClusterRestart::class,
                Console\Cluster\SetupCluster::class,

                Console\DataStreams\CreateDataStream::class,
                Console\DataStreams\DeleteDataStream::class,

                Console\Documents\Reindex::class,

                Console\Indices\UpdateIndex::class,
                Console\Indices\CreateIndexTemplate::class,
                Console\Indices\CreateIndex::class,
                Console\Indices\CountFields::class,
                Console\Indices\DeleteIndex::class,
                Console\Indices\UpdateIndexSettings::class,

                Console\Nodes\ExcludeNodeAllocation::class,
                Console\Nodes\UpgradeNode::class,

                Console\Security\ConfigureHttpTLS::class,
                Console\Security\ConfigureTransportTLS::class,
                Console\Security\UpdateCertificateAuthority::class,

                Console\Shards\ToggleShardAllocation::class,
            ]);
        }
    }
}
