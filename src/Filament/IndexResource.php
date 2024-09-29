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

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

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
                TextEntry::make('_id'),
                TextEntry::make('cluster'),
                TextEntry::make('name'),
                TextEntry::make('health'),
                TextEntry::make('status'),
                TextEntry::make('docs'),
                TextEntry::make('primary_size')
                    ->formatStateUsing(fn($record) => Number::fileSize($record->primary_size, 1)),
                TextEntry::make('total_size')
                    ->formatStateUsing(fn($record) => Number::fileSize($record->total_size, 1)),
                TextEntry::make('shards'),
                TextEntry::make('deleted_docs'),
                TextEntry::make('Json')
                    // ->getStateUsing(fn($record) => '<pre>' . $record->toJson(JSON_PRETTY_PRINT) . '</pre>')
                    ->getStateUsing(static function ($record): string {
                        $settings = $record->getSettings();
                        $mappings = $record->getMappings();
                        return '<pre>' . json_encode(compact('settings', 'mappings'), JSON_PRETTY_PRINT) . '</pre>';

                        return '<pre>' . $record->toJson(JSON_PRETTY_PRINT) . '</pre>';
                    })
                    ->html(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cluster')->badge()->color('gray'),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('health')
                    ->badge()
                    ->color(fn($record) => match ($record->health) {
                        'green' => 'success',
                        'yellow' => 'warning',
                        'red' => 'danger',
                        default => 'primary',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn($record) => match ($record->status) {
                        'open' => 'primary',
                        'closed' => 'danger',
                        default => 'danger',
                    }),
                TextColumn::make('docs')->numeric(locale: app()->getLocale())->alignRight()->sortable(),
                TextColumn::make('primary_size')->sortable()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->primary_size, 1))
                    ->alignRight(),
                TextColumn::make('total_size')
                    ->sortable()
                    ->formatStateUsing(fn($record) => Number::fileSize($record->total_size, 1))
                    ->alignRight(),
                TextColumn::make('Doc size')
                    ->getStateUsing(static fn($record) => Number::fileSize($record->total_size / max(1, $record->docs), 1)),
                TextColumn::make('shards')->sortable()->numeric(locale: app()->getLocale())->alignRight(),
                TextColumn::make('deleted_docs')->sortable()->numeric(locale: app()->getLocale())->alignRight(),
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

    public static function getNavigationBadge(): ?string
    {
        $model = static::getModel();
        return format_number(
            (new $model)->count()
        );
    }
}
