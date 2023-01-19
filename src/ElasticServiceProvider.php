<?php

namespace Elastico;

use Elastico\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

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
    }

    public function register()
    {
        // Add Eloquent Database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('elastic', function ($config, $name) {
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
