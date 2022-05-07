<?php

namespace Elastico;

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

        // Model::setEventDispatcher($this->app['events']);
    }

    public function register()
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/elastico.php',
            key: 'elastico'
        );

        $this->app->singleton(ConnectionResolverInterface::class, function () {
            return new ConnectionResolver($this->app['config']['elastico']['connections']);
        });
    }
}
