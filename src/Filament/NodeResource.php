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
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Elastico\Filament\NodeResource\Pages\ViewNode;
use Filament\Infolists\Components\KeyValueEntry;
use Illuminate\Support\Number;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Elasticsearch';

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('id'),
                TextEntry::make('name'),
                TextEntry::make('cluster'),

                TextEntry::make('fs.total.total_in_bytes')
                    ->formatStateUsing(fn($record) => Number::fileSize($record->fs['total']['total_in_bytes']))
                    ->label('Filesystem Total'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cluster')->badge()->color('gray'),
                TextColumn::make('name')->searchable(),

                TextColumn::make('fs.total.total_in_bytes')
                    ->label('FS Total')
                    ->formatStateUsing(fn($record) => Number::fileSize($record->fs['total']['total_in_bytes']))
                    ->sortable(),

                TextColumn::make('fs.total.available_in_bytes')
                    ->label('FS Available')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->fs['total']['available_in_bytes']))
                    ->color(fn($record) => $record->fs['total']['available_in_bytes'] < 100_000_000_000 ? 'danger' : 'success'), // 100GB


                TextColumn::make('os.mem.used_in_bytes')
                    ->label('Memory Used')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->os['mem']['used_in_bytes'])),
                TextColumn::make('os.mem.total_in_bytes')
                    ->label('Memory Total')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->os['mem']['total_in_bytes'])),
                TextColumn::make('os.mem.used_percent')
                    ->label('Memory Used %')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::percentage($record->os['mem']['used_percent'])),


                TextColumn::make('os.cpu.percent')
                    ->label('CPU %')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::percentage($record->os['cpu']['percent']))
                    ->color(fn($record) => $record->os['cpu']['percent'] > 90 ? 'danger' : 'success'),
                TextColumn::make('os.cpu.load_average.1m')
                    ->label('CPU Load 1m')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => $record->os['cpu']['load_average']['1m']),
                TextColumn::make('os.cpu.load_average.5m')
                    ->label('CPU Load 5m')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => $record->os['cpu']['load_average']['5m']),
                TextColumn::make('os.cpu.load_average.15m')
                    ->label('CPU Load 15m')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => $record->os['cpu']['load_average']['15m']),


                TextColumn::make('jvm.mem.heap_used_in_bytes')
                    ->label('JVM Heap Used')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->jvm['mem']['heap_used_in_bytes'])),
                TextColumn::make('jvm.mem.heap_max_in_bytes')
                    ->label('JVM Heap Max')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->jvm['mem']['heap_max_in_bytes'])),
                TextColumn::make('jvm.mem.heap_used_percent')
                    ->label('JVM Heap Used %')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::percentage($record->jvm['mem']['heap_used_percent']))
                    ->color(fn($record) => $record->jvm['mem']['heap_used_percent'] > 90 ? 'danger' : 'success'),
                TextColumn::make('jvm.mem.non_heap_used_in_bytes')
                    ->label('JVM Non Heap Used')
                    ->sortable()
                    ->alignRight()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->jvm['mem']['non_heap_used_in_bytes'])),

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
            'view' => ViewNode::route('/{record}'),
            // 'create' => Pages\CreateHost::route('/create'),
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
