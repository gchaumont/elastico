<?php

namespace Elastico\Filament;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Elastico\Filament\IndexResource\Widgets\IndexStats;
use Elastico\Filament\NodeResource\Widgets\NodeStats;
use Elastico\Filament\Widgets\NodeStatsTable;
use Elastico\Filament\Widgets\ClusterStats;

class ElasticoDashboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/elastico';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Dashboard';

    protected static string | \UnitEnum | null $navigationGroup = 'Elasticsearch';

    public function getWidgets(): array
    {
        return [
            ClusterStats::class,
            NodeStatsTable::class,
        ];
    }


    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('connection')
                            ->label('Cluster')
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
