<?php

namespace Elastico\Filament;

use Carbon\CarbonInterface;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Elastico\Filament\SnapshotResource\Pages\ListSnapshots;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Elastico\Filament\SnapshotResource\Pages\ViewSnapshot;
use Elastico\Models\Snapshot;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Illuminate\Support\Number;

class SnapshotResource extends Resource
{
    protected static ?string $model = Snapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Elasticsearch';

    protected static ?string $recordTitleAttribute = 'snapshot';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('snapshot'),
                TextEntry::make('repository')->badge(),
                IconEntry::make('include_global_state')
                    ->boolean(),
                Fieldset::make('')
                    ->schema([
                        TextEntry::make('state'),
                        TextEntry::make('duration_in_millis')
                            ->label('Duration')
                            ->tooltip(fn($record) => Number::format($record->duration_in_millis, locale: app()->getLocale()) . 'ms')
                            ->formatStateUsing(fn($state) => now()->subMilliseconds($state)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE)),
                        TextEntry::make('start_time')
                            ->since()
                            ->dateTimeTooltip(),
                        TextEntry::make('end_time')
                            ->since()
                            ->dateTimeTooltip(),

                    ]),
                TextEntry::make('indices')
                    ->bulleted(),
                TextEntry::make('data_streams')
                    ->bulleted(),
                KeyValueEntry::make('shards')
                    ->columnSpanFull(),
                KeyValueEntry::make('failures')
                    ->columnSpanFull(),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('snapshot')->searchable(),
                TextColumn::make('repository')->searchable()->badge(),
                TextColumn::make('state'),
                TextColumn::make('start_time')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('end_time')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('duration_in_millis')
                    ->label('Duration')
                    ->tooltip(fn($state) =>   Number::format($state, locale: app()->getLocale()) . 'ms')
                    ->formatStateUsing(fn($state) => now()->subMilliseconds($state)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE)),
            ])
            ->filters([])
            ->recordUrl(fn(Snapshot $record) => static::getUrl('view', ['record' => $record]))
            // ->actions([
            //     ViewAction::make()
            //         ->slideOver(),
            //     // Tables\Actions\EditAction::make(),
            // ])
            ->bulkActions([])
            // ->deferLoading()
            ->defaultSort('name', 'asc')
            ->poll('5s');
    }

    public static function getRelations(): array
    {
        return [];
    }



    public static function getPages(): array
    {
        return [
            'index' => ListSnapshots::route('/'),
            'view' => ViewSnapshot::route('/{record}'),
        ];
    }
}
