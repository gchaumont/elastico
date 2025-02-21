<?php

namespace Elastico\Filament\Widgets;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class ClusterWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        if (empty($this->filters['connection'])) {
            return [];
        }

        $cluster_health = DB::connection($this->filters['connection'])
            ->getClient()
            ->cluster()
            ->health()
            ->asArray();

        $cluster_stats = DB::connection($this->filters['connection'])
            ->getClient()
            ->cluster()
            ->stats()
            ->asArray();


        $fs_used_percent = ($cluster_stats['nodes']['fs']['total_in_bytes'] - $cluster_stats['nodes']['fs']['free_in_bytes']) / $cluster_stats['nodes']['fs']['total_in_bytes'];

        return [
            Stat::make('Cluster Status', $cluster_health['status'])
                ->color(match ($cluster_health['status']) {
                    'green' => 'success',
                    'yellow' => 'warning',
                    'red' => 'danger',
                    default => 'primary',
                })
                ->description("Cluster: " . $cluster_stats['cluster_name']),

            Stat::make('Nodes', Number::format($cluster_health['number_of_nodes'], locale: app()->getLocale()))
                ->description("Data nodes " . Number::format($cluster_health['number_of_data_nodes'], locale: app()->getLocale())),

            Stat::make('Primary Shards ', Number::format($cluster_health['active_primary_shards'], locale: app()->getLocale()))
                ->description("Active Shards " . Number::format($cluster_health['active_shards'], locale: app()->getLocale())),



            Stat::make("Relocating Shards ", Number::format($cluster_health['relocating_shards'], locale: app()->getLocale()))
                ->description(Number::format($cluster_health['relocating_shards'], locale: app()->getLocale()) . " relocating shards")
                ->color(match (true) {
                    $cluster_health['relocating_shards'] < 1 => 'success',
                    $cluster_health['relocating_shards'] < 10 => 'warning',
                    default => 'danger',
                }),

            Stat::make("Unassigned Shards ", Number::format($cluster_health['unassigned_shards'], locale: app()->getLocale()))
                ->description(Number::format($cluster_health['unassigned_shards'], locale: app()->getLocale()) . " unassigned shards")
                ->color(match (true) {
                    $cluster_health['unassigned_shards'] < 1 => 'success',
                    $cluster_health['unassigned_shards'] < 10 => 'warning',
                    default => 'danger',
                }),



            Stat::make("Indices", Number::format($cluster_stats['indices']['count'], locale: app()->getLocale())),

            Stat::make('Documents', Number::format($cluster_stats['indices']['docs']['count'], locale: app()->getLocale()))
                ->description("Deleted " . Number::format($cluster_stats['indices']['docs']['deleted'], locale: app()->getLocale())),


            Stat::make("OS Mem used", Number::percentage($cluster_stats['nodes']['os']['mem']['used_percent']))
                ->description("Used " . Number::fileSize($cluster_stats['nodes']['os']['mem']['used_in_bytes']) . " of " . Number::fileSize($cluster_stats['nodes']['os']['mem']['total_in_bytes'])),

            Stat::make("Indices Store Size", Number::fileSize($cluster_stats['indices']['store']['size_in_bytes'])),
            Stat::make('Disk Usage',  Number::fileSize($cluster_stats['nodes']['fs']['total_in_bytes'] - $cluster_stats['nodes']['fs']['free_in_bytes']))
                ->description("Used " . Number::percentage(100 * $fs_used_percent) . " of " . Number::fileSize($cluster_stats['nodes']['fs']['total_in_bytes']))
                ->color(match (true) {
                    $fs_used_percent < 0.5 => 'success',
                    $fs_used_percent < 0.8 => 'warning',
                    default => 'danger',
                }),

            // Snapshots
            Stat::make('Snapshots', Number::format($cluster_stats['snapshots']['current_counts']['snapshots'], locale: app()->getLocale()))
            // ->description("Successful " . Number::format($cluster_stats['snapshots']['current_counts'], locale: app()->getLocale())),
        ];
    }

    // protected function getCards(): array
    // {


    //     $total_filesytem = Node::sum('filesystem_total');
    //     $used_filesytem = Node::sum('filesystem_used_bytes');

    //     return [
    //         Stat::make('Total Nodes', Number::format(Node::count(), locale: app()->getLocale())),
    //         Stat::make('Filesystem', Number::fileSize($total_filesytem)),
    //         Stat::make('Used Filesystem', Number::fileSize($used_filesytem))
    //             ->description(Number::percentage($used_filesytem / $total_filesytem * 100) . ' used'),
    //         Stat::make('Free Filesystem', Number::fileSize(Node::sum('filesystem_available_bytes'))),
    //         Stat::make('Total Memory', Number::fileSize(Node::sum('memory_max_bytes'))),
    //         Stat::make('Memory', Number::percentage(Node::avg('memory_current_percent'))),
    //         Stat::make('CPU', Number::format(Node::avg('cpu_load_15m'), locale: app()->getLocale())),
    //     ];
    // }
}
