<?php

namespace Elastico\Filament\NodeResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Elastico\Filament\NodeResource;
use Elastico\Filament\NodeResource\Widgets\NodeStats;

class ListNodes extends ListRecords
{
    protected static string $resource = NodeResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
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
