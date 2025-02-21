<?php

namespace Elastico\Filament\Widgets;

use App\Filament\Columns\NumericColumn;
use Carbon\Carbon;
use Elastico\Filament\NodeResource;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class NodeStatsTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nodes')
            ->query(NodeResource::getEloquentQuery())
            ->defaultPaginationPageOption(5)
            ->poll('2s')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('cluster'),

                TextColumn::make('os.cpu.percent')
                    ->label('CPU %')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::percentage($record->os['cpu']['percent']))
                    ->color(fn($record) => $record->os['cpu']['percent'] > 90 ? 'danger' : 'success'),

                TextColumn::make('jvm.mem.heap_used_percent')
                    ->label('JVM Heap Used %')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::percentage($record->jvm['mem']['heap_used_percent']))
                    ->color(fn($record) => $record->jvm['mem']['heap_used_percent'] > 90 ? 'danger' : 'success'),

                TextColumn::make('fs.total.available_in_bytes')
                    ->label('FS Available')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->fs['total']['available_in_bytes']))
                    ->color(fn($record) => $record->fs['total']['available_in_bytes'] < 100_000_000_000 ? 'danger' : 'success'), // 100GB

                TextColumn::make('indices.shard_stats.total_count')
                    ->label('Shards')
                    ->sortable()
                    ->alignRight(),
            ])
            ->actions([]);
    }
}
