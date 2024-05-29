<?php

namespace Elastico\Filament;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Crawler\Models\Url;
use Filament\Forms\Components\Select;
use Crawler\CrawlerManager;
use Filament\Forms\Components\TextInput;
use Crawler\Actions\Urls\FetchUrl;
use Crawler\Filament\Actions\CreateJob;
use Crawler\Filament\Resources\UrlResource;
use Filament\Pages\Dashboard;
use Illuminate\Support\Str;
use Elastico\Filament\IndexResource\Widgets\IndexStats;
use Elastico\Filament\NodeResource\Widgets\NodeStats;

class ElasticoDashboard extends Dashboard
{
    protected static string $routePath = '/elastico';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationGroup = 'Elasticsearch';

    public function getWidgets(): array
    {
        return [
            IndexStats::class,
            NodeStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
