<?php

namespace Elastico\Filament;

use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\QueryException;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Elastico\Models\Index;
use Filament\Tables\Columns\Summarizers\Sum;
use Elastico\Filament\IndexResource\Pages\ListIndices;
use Elastico\Filament\NodeResource\Pages\ListNodes;
use Elastico\Filament\NodeResource\Widgets\NodeStats;
use Elastico\Models\Node;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Elasticsearch';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cluster')->badge()->color('gray'),
                TextColumn::make('name')->searchable(),

                TextColumn::make('filesystem_total')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => formatBytes($record->filesystem_total)),
                TextColumn::make('filesystem_used_bytes')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => formatBytes($record->filesystem_used_bytes)),
                TextColumn::make('filesystem_used_percent')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => round($record->filesystem_used_percent) . '%'),

                TextColumn::make('memory_current_bytes')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => formatBytes($record->memory_current_bytes)),
                TextColumn::make('memory_current_percent')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => round($record->memory_current_percent) . '%'),

                TextColumn::make('cpu')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => round($record->cpu) . '%'),
                TextColumn::make('cpu_load_1m')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => ($record->cpu_load_1m)),
                TextColumn::make('cpu_load_5m')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => ($record->cpu_load_5m)),
                TextColumn::make('cpu_load_15m')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => ($record->cpu_load_15m)),

                TextColumn::make('jvm_heap_used_in_bytes')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => formatBytes($record->jvm_heap_used_in_bytes)),
                TextColumn::make('jvm_heap_used_percent')->sortable()->numeric()->alignRight()->formatStateUsing(fn ($record) => round($record->jvm_heap_used_percent) . '%'),


            ])
            ->filters([])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ])
            // ->deferLoading()
            ->defaultSort('name', 'asc')
            ->poll('5s');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getWidgets(): array
    {
        return [
            NodeStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNodes::route('/'),
            // 'create' => Pages\CreateHost::route('/create'),
            // 'view' => ViewCrawlJob::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $model = static::getModel();
        return format_number(
            (new $model)->count()
        );
    }
}
