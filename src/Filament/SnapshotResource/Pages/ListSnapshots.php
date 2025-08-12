<?php

namespace Elastico\Filament\SnapshotResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Resources\Pages\ListRecords;
use Elastico\Filament\SnapshotResource;

class ListSnapshots extends ListRecords
{
    protected static string $resource = SnapshotResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
