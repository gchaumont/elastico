<?php

namespace Elastico;

use Elastico\Filament\NodeResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Navigation\NavigationGroup;
use Elastico\Filament\IndexResource;
use Elastico\Filament\ElasticoDashboard;

class ElasticoPlugin implements Plugin
{
    protected string $navigationGroup;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'elastico';
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                ElasticoDashboard::class,
            ])
            ->resources([
                // 'clusters' => ClusterResource::class,
                'nodes' => NodeResource::class,
                'indices' => IndexResource::class,
                // 'shards' => ShardResource::class, 
                // 'snapshots' => SnapshotResource::class,
            ])
            ->widgets([]);
    }

    public function boot(Panel $panel): void
    {

        if (isset($this->navigationGroup)) {
            ElasticoDashboard::navigationGroup($this->navigationGroup);
            NodeResource::navigationGroup($this->navigationGroup);
            IndexResource::navigationGroup($this->navigationGroup);
        }
    }
}
