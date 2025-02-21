<?php

namespace Elastico\Filament\IndexResource\Widgets;

use Elastico\Models\Index;
use Elastico\Filament\IndexResource\Pages\ListIndices;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
        return [
            Stat::make('Total indices', format_number($this->getPageTableQuery()->count())),
            Stat::make('Total size', formatBytes($this->getPageTableQuery()->sum('total_size')))
                ->description('Primary size', formatBytes($this->getPageTableQuery()->sum('primary_size'))),
            Stat::make('Documents', format_number($this->getPageTableQuery()->sum('docs'))),
            Stat::make('Unhealthy', format_number($this->getPageTableQuery()->whereIn('health', ['red', 'yellow'])->count()))
                ->color('danger'),
        ];
    }
}
