<?php

namespace Elastico\Filament\NodeResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Elastico\Filament\NodeResource;
use Elastico\Filament\NodeResource\Widgets\NodeStats;
use Filament\Support\Enums\MaxWidth;

class ListNodes extends ListRecords
{
    protected static string $resource = NodeResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    public function getHeaderWidgets(): array
    {
        return [
            NodeStats::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            // null => Tab::make('All Clusters'),
            // ...collect(Node::all())
            //     ->pluck('cluster')
            //     ->unique()
            //     ->mapWithKeys(fn($cluster) => [$cluster => Tab::make($cluster)])
            //     ->all()
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
