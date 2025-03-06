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
use Elastico\Filament\IndexResource\Widgets\IndexStats;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Model;
use Filament\Infolists\Components\TextEntry;

class IndexResource extends Resource
{
    protected static ?string $model = Index::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Elasticsearch';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $model = new Index();
        try {
            $model->query()->delete();
            $model->insert($model->getRows());
        } catch (QueryException) {
            try {
                $model->migrate();
            } catch (QueryException) {
                // table already exists
            }
        }

        return parent::getEloquentQuery();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('uuid'),
                TextEntry::make('name'),
                TextEntry::make('health')
                    ->badge()
                    ->color(fn($record) => match ($record->health) {
                        'green' => 'success',
                        'yellow' => 'warning',
                        'red' => 'danger',
                        default => 'primary',
                    }),
                TextEntry::make('status'),
                TextEntry::make('primaries')
                    ->getStateUsing(static function ($record): string {
                        return '<pre>' . json_encode($record->primaries, JSON_PRETTY_PRINT) . '</pre>';
                    })
                    ->columnSpanFull()
                    ->html(),
                TextEntry::make('total')
                    ->getStateUsing(static function ($record): string {
                        return '<pre>' . json_encode($record->total, JSON_PRETTY_PRINT) . '</pre>';
                    })
                    ->columnSpanFull()
                    ->html(),
                TextEntry::make('settings')
                    ->columnSpanFull()
                    // ->getStateUsing(fn($record) => '<pre>' . $record->toJson(JSON_PRETTY_PRINT) . '</pre>')
                    ->getStateUsing(static function ($record): string {
                        $settings = $record->getSettings();
                        return '<pre>' . json_encode($settings, JSON_PRETTY_PRINT) . '</pre>';
                    })
                    ->html(),
                TextEntry::make('mappings')
                    ->columnSpanFull()
                    // ->getStateUsing(fn($record) => '<pre>' . $record->toJson(JSON_PRETTY_PRINT) . '</pre>')
                    ->getStateUsing(static function ($record): string {
                        $mappings = $record->getMappings();
                        return '<pre>' . json_encode($mappings, JSON_PRETTY_PRINT) . '</pre>';
                    })
                    ->html(),
            ]);
    }


    public static function table(Table $table): Table
    {


        return $table
            ->columns([
                TextColumn::make('cluster')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('health')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => match ($record->health) {
                        'green' => 'success',
                        'yellow' => 'warning',
                        'red' => 'danger',
                        default => 'primary',
                    }),
                // TextColumn::make('status')
                //     ->badge()
                //     ->color(fn($record) => match ($record->status) {
                //         'open' => 'primary',
                //         'closed' => 'danger',
                //         default => 'danger',
                //     }),
                TextColumn::make('total.docs.count')
                    ->label('Documents')
                    ->numeric(locale: app()->getLocale())
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('total.store.size_in_bytes')
                    ->label('Data')
                    ->sortable()
                    ->formatStateUsing(fn($state) => Number::fileSize($state, 1))
                    ->alignRight(),
                TextColumn::make('primary.store.size_in_bytes')
                    ->label('Primary size')
                    ->sortable()
                    ->formatStateUsing(fn($state) => Number::fileSize($state, 1))
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Doc size')
                    ->getStateUsing(static fn($record) => Number::fileSize($record->total->store->size_in_bytes / max(1, $record->total->docs->count), 1))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total.shard_stats.total_count')
                    ->label('Shards')
                    ->sortable()
                    ->numeric(locale: app()->getLocale())
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total.docs.deleted')
                    ->sortable()
                    ->numeric(locale: app()->getLocale())
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Index Rate
                TextColumn::make('total.indexing.index_total')
                    ->label('Index Rate')
                    ->sortable()
                    ->numeric(locale: app()->getLocale())
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total.search.query_total')
                    ->sortable()
                    ->label('Search Rate')
                    ->numeric(locale: app()->getLocale())
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('health')
                    ->options([
                        'green' => 'green',
                        'yellow' => 'yellow',
                        'red' => 'red',
                    ]),
            ])
            ->actions([
                // see the mappings and settings
                ViewAction::make()
                    ->slideOver(),

                Action::make('delete')
                    ->action(fn($record) => DB::connection('elastic')->getClient()->indices()->delete(['index' => $record->name]))
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->modalIcon('heroicon-o-trash')
                    ->modalHeading(static fn(Index $record) => "Delete index?")
                    ->modalDescription(static fn(Index $record) => "Are you sure you want to delete {$record->name}?")
                    ->modalSubmitActionLabel('Delete')
                    ->color('danger'),
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ])
            // ->deferLoading()
            ->defaultSort('total_size', 'desc')
            ->poll('5s');
    }


    public static function getRelations(): array
    {
        return [];
    }

    public static function getWidgets(): array
    {
        return [
            IndexStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndices::route('/'),
            // 'create' => Pages\CreateHost::route('/create'),
            // 'view' => ViewCrawlJob::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            // 'Health' => $record->health,
            'Docs' => Number::format($record->docs, locale: app()->getLocale()),
            'Size' => Number::fileSize($record->total_size, 1),
        ];
    }

    // public static function getNavigationBadge(): ?string
    // {
    //     $model = static::getModel();
    //     return format_number(
    //         (new $model)->count()
    //     );
    // }
}
