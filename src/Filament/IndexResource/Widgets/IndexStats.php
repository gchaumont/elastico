<?php

namespace Elastico\Filament\IndexResource\Widgets;

use Elastico\Models\Index;
use Elastico\Filament\IndexResource\Pages\ListIndices;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class IndexStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListIndices::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // 'total_size' => $value['total']['store']['size_in_bytes'],
        // 'primary_size' => $value['primaries']['store']['size_in_bytes'],
        // 'health' => $value['health'],
        // 'status' => $value['status'],
        // 'docs' => $value['total']['docs']['count'],
        // 'deleted_docs' => $value['total']['docs']['deleted'],
        // 'shards' => $value['total']['shard_stats']['total_count'],
        return [
            Stat::make('Indices', Number::format($this->getPageTableQuery()->count(), locale: app()->getLocale())),
            Stat::make('Size', Number::fileSize($this->getPageTableQuery()->sum('total->store->size_in_bytes')))
                ->description('Primary size ' . Number::fileSize($this->getPageTableQuery()->sum('primaries->store->size_in_bytes'))),
            Stat::make('Documents', Number::format($this->getPageTableQuery()->sum('total->docs->count'), locale: app()->getLocale())),
            Stat::make('Unhealthy', Number::format($this->getPageTableQuery()->whereIn('health', ['red', 'yellow'])->count(), locale: app()->getLocale()))
                ->color('danger'),
        ];
    }
}
