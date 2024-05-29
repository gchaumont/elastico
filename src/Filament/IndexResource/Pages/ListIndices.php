<?php

namespace Elastico\Filament\IndexResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Elastico\Models\Index;
use Filament\Resources\Pages\ListRecords\Tab;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Elastico\Filament\IndexResource;
use Elastico\Filament\IndexResource\Widgets\IndexStats;

class ListIndices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = IndexResource::class;

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    public function getHeaderWidgets(): array
    {
        return [
            IndexStats::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Clusters'),
            ...collect(Index::all())
                ->pluck('cluster')
                ->unique()
                ->mapWithKeys(fn ($cluster) => [$cluster => Tab::make($cluster)])
                ->all()
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
