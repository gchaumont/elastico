<?php

namespace Elastico\Filament\RepositoryResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Resources\Pages\ListRecords;
use Elastico\Filament\RepositoryResource;

class ListRepositories extends ListRecords
{
    protected static string $resource = RepositoryResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
