<?php

namespace Elastico\Filament\Widgets;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class ClusterStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        if (empty($this->pageFilters['connection'])) {
            return [];
        }

        $connection = $this->pageFilters['connection'];

        $cluster_health = DB::connection($connection)
            ->getClient()
            ->cluster()
            ->health()
            ->asArray();

        $cluster_stats = DB::connection($connection)
            ->getClient()
            ->cluster()
            ->stats()
            ->asArray();

        $indices_stats = DB::connection($connection)
            ->getClient()
            ->indices()
            ->stats()
            ->asArray();

        $fs_used_percent = ($cluster_stats['nodes']['fs']['total_in_bytes'] - $cluster_stats['nodes']['fs']['free_in_bytes']) / $cluster_stats['nodes']['fs']['total_in_bytes'];

        $jvm_mem_used_percent = $cluster_stats['nodes']['jvm']['mem']['heap_used_in_bytes'] / $cluster_stats['nodes']['jvm']['mem']['heap_max_in_bytes'];

        $unhealthy_indices = collect($indices_stats['indices'])
            ->filter(fn($index) => $index['health'] !== 'green')
            ->count();

        return [
            Stat::make('Cluster Status', $cluster_health['status'])
                ->color(match ($cluster_health['status']) {
                    'green' => 'success',
                    'yellow' => 'warning',
                    'red' => 'danger',
                    default => 'primary',
                })
                ->description("Cluster: " . $cluster_stats['cluster_name']),

            Stat::make('Nodes', Number::format($cluster_health['number_of_nodes'], locale: app()->getLocale())),

            Stat::make("Indices", Number::format($cluster_stats['indices']['count'], locale: app()->getLocale()))
                ->description($unhealthy_indices ? Number::format($unhealthy_indices, locale: app()->getLocale()) . " unhealthy indices" : 'All indices are healthy')
                ->color($unhealthy_indices ? 'danger' : 'success'),

            // TOTAL JVM Memory usage / AVAILABLE
            Stat::make("Memory", Number::fileSize($cluster_stats['nodes']['jvm']['mem']['heap_used_in_bytes'], precision: 1) . ' / ' . Number::fileSize($cluster_stats['nodes']['jvm']['mem']['heap_max_in_bytes'], precision: 1))
                ->description(Number::percentage($jvm_mem_used_percent * 100) . ' used')
                ->color(match (true) {
                    $jvm_mem_used_percent < 0.6 => 'success',
                    $jvm_mem_used_percent < 0.8 => 'warning',
                    default => 'danger',
                }),

            // Total Number of Shards
            Stat::make('Shards', Number::format($cluster_health['active_shards'], locale: app()->getLocale()))
                ->description(Number::format($cluster_health['active_primary_shards']) . " primary /  " . Number::format($cluster_health['active_shards'] - $cluster_health['active_primary_shards'], locale: app()->getLocale()) . " replica"),
            Stat::make('Unassigned Shards', Number::format($cluster_health['unassigned_shards'], locale: app()->getLocale()))
                ->description($cluster_health['unassigned_shards'] ? Number::format($cluster_health['unassigned_shards'], locale: app()->getLocale()) . " unassigned shards" : null)
                ->color(match (true) {
                    $cluster_health['unassigned_shards'] < 1 => 'success',
                    $cluster_health['unassigned_shards'] < 10 => 'warning',
                    default => 'danger',
                }),


            Stat::make('Documents', Number::format($cluster_stats['indices']['docs']['count'], locale: app()->getLocale())),

            // Data size 
            Stat::make('Data', Number::fileSize($cluster_stats['indices']['store']['size_in_bytes']))
                ->description("Used " . Number::percentage(100 * $fs_used_percent) . " of " . Number::fileSize($cluster_stats['nodes']['fs']['total_in_bytes']))
                ->color(match (true) {
                    $fs_used_percent < 0.5 => 'success',
                    $fs_used_percent < 0.8 => 'warning',
                    default => 'danger',
                }),
        ];
    }
}
