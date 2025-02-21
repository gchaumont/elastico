<?php

namespace Elastico\Filament;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Elastico\Filament\IndexResource\Widgets\IndexStats;
use Elastico\Filament\NodeResource\Widgets\NodeStats;
use Elastico\Filament\Widgets\ClusterWidget;
use Elastico\Filament\Widgets\NodeStatsTable;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Elastico\Filament\Widgets\ElasticStatsOverviewWidget;
use Elastico\Filament\Widgets\SummaryWidget;

class ElasticoDashboard extends Dashboard
{
    use Dashboard\Concerns\HasFiltersForm;

    protected static string $routePath = '/elastico';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationGroup = 'Elasticsearch';

    public function getWidgets(): array
    {
        return [
            SummaryWidget::class,
            ClusterWidget::class,
            NodeStats::class,
            IndexStats::class,
            NodeStatsTable::class,
            ElasticStatsOverviewWidget::class,
        ];
    }


    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('connection')
                            ->default(collect(config('database.connections'))
                                ->filter(fn($connection) => $connection['driver'] === 'elastic')
                                ->keys()->first())
                            ->options(collect(config('database.connections'))
                                ->filter(fn($connection) => $connection['driver'] === 'elastic')
                                ->map(fn($connection, $name) => $name)
                                ->all()),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
