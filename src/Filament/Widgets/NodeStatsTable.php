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
            ->query(NodeResource::getEloquentQuery())
            ->defaultPaginationPageOption(5)
            ->poll('5s')
            // ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('cluster'),

                TextColumn::make('cpu')
                    ->suffix('%')
                    ->numeric(),
                TextColumn::make('memory_current_percent')
                    ->label('Memory used')
                    ->numeric()
                    ->suffix('%'),
                TextColumn::make('filesystem_available_bytes')
                    ->label('Disk space available')
                    ->numeric()
                    ->formatStateUsing(fn($record): string => Number::fileSize($record->filesystem_available_bytes)),
                TextColumn::make('jvm_heap_used_percent')
                    ->label('JVM heap used')
                    ->numeric()
                    ->suffix('%'),

                // Tables\Columns\TextColumn::make('currency')
                //     ->getStateUsing(fn($record): ?string => Currency::find($record->currency)?->name ?? null)
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('total_price')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('shipping_price')
                //     ->label('Shipping cost')
                //     ->searchable()
                //     ->sortable(),
            ])
            ->actions([
                // Tables\Actions\Action::make('open')
                //     ->url(fn(Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
