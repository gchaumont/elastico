<?php

namespace Elastico;

use Elastico\Controllers\ElasticoController;
use Elastico\Models\Model;
use Illuminate\Support\ServiceProvider;

/**
 *  Elasticsearch ServiceProvider.
 */
class ElasticServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/elastico.php' => config_path('elastico.php'),
        ]);

        Model::setConnectionResolver(resolve(ConnectionResolverInterface::class));

        $this->registerCommands();

        // Model::setEventDispatcher($this->app['events']);

        $this->registerForwardingRoutes(
            $this->app['config']['elastico']['forwarding']
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/elastico.php',
            key: 'elastico'
        );

        $this->app->singleton(ConnectionResolverInterface::class, function () {
            return (new ConnectionResolver(
                connections: $this->app['config']['elastico']['connections'],
                forwarding: $this->app['config']['elastico']['forwarding'],
            ))
                ->setDefaultConnection($this->app['config']['elastico']['default'])
            ;
        });
    }

    public function registerForwardingRoutes(array $forwarding): void
    {
        $router = $this->app->make(\Illuminate\Routing\Router::class);

        foreach ($forwarding as $connection => $config) {
            $router->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
                ->domain($config['domain'])
                ->any(($config['path'] ?? '').'/{any}', [ElasticoController::class, 'emulateElastic'])
                ->where('any', '.*')
    ;
        }
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

                Console\Indices\UpdateIndex::class,
                Console\Indices\CreateIndexTemplate::class,
                Console\Indices\CreateIndex::class,
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
