<?php

namespace Elastico\Filament\NodeResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Elastico\Models\Node;
use Filament\Resources\Pages\ListRecords\Tab;
use Elastico\Filament\NodeResource;
use Elastico\Filament\NodeResource\Widgets\NodeStats;

class ListNodes extends ListRecords
{
    protected static string $resource = NodeResource::class;

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
            null => Tab::make('All Clusters'),
            ...collect(Node::all())
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
