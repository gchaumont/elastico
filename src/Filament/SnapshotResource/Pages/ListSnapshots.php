<?php

namespace Elastico\Filament\SnapshotResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Elastico\Filament\SnapshotResource;
use Filament\Support\Enums\MaxWidth;

class ListSnapshots extends ListRecords
{
    protected static string $resource = SnapshotResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
