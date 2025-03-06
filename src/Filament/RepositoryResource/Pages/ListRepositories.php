<?php

namespace Elastico\Filament\RepositoryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Elastico\Filament\RepositoryResource;
use Filament\Support\Enums\MaxWidth;

class ListRepositories extends ListRecords
{
    protected static string $resource = RepositoryResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
