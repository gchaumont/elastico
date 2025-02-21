<?php

namespace Elastico\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\DB;

class ElasticStatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        if (empty($this->filters['connection'])) {
            return [];
        }

        $cluster_pending_tasks = DB::connection($this->filters['connection'])
            ->getClient()
            ->cluster()
            ->pendingTasks()
            ->asArray();

        $nodes_stats = DB::connection($this->filters['connection'])
            ->getClient()
            ->nodes()
            ->stats()
            ->asArray();

        $indices_stats = DB::connection($this->filters['connection'])
            ->getClient()
            ->cat()
            ->indices(['v' => true, 'h' => 'index,health,status,docs.count,store.size,pri,rep'])
            ->asString();

        // dump($cluster_pending_tasks, $nodes_stats, $indices_stats);

        return [




            // 🔥 6. Search and Indexing Performance (_nodes/stats/indices)
            //         Key Stats:
            // •	indices.search.query_total: Total search queries
            // •	indices.search.query_time_in_millis: Time taken for queries
            // •	indices.indexing.index_total: Total indexed documents
            // •	indices.indexing.index_time_in_millis: Time taken for indexing


            // Stat::make('Search Queries', Number::format($nodes_stats['nodes']['indices']['search']['query_total'], locale: app()->getLocale()))
            //     ->description("Time " . Number::format($nodes_stats['nodes']['indices']['search']['query_time_in_millis'], locale: app()->getLocale()) . " ms"),
            // Stat::make('Indexed Documents', Number::format($nodes_stats['nodes']['indices']['indexing']['index_total'], locale: app()->getLocale()))
            //     ->description("Time " . Number::format($nodes_stats['nodes']['indices']['indexing']['index_time_in_millis'], locale: app()->getLocale()) . " ms"),


            // Stat::make('New customers', $formatNumber($newCustomers))
            //     ->description('3% decrease')
            //     ->descriptionIcon('heroicon-m-arrow-trending-down')
            //     ->chart([17, 16, 14, 15, 14, 13, 12])
            //     ->color('danger'),
            // Stat::make('New orders', $formatNumber($newOrders))
            //     ->description('7% increase')
            //     ->descriptionIcon('heroicon-m-arrow-trending-up')
            //     ->chart([15, 4, 10, 2, 12, 4, 12])
            //     ->color('success'),
        ];
    }
}
