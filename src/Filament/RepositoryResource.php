<?php

namespace Elastico\Filament;

use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Elastico\Filament\RepositoryResource\Pages\ListRepositories;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Elastico\Filament\RepositoryResource\Pages\ViewRepository;
use Elastico\Models\Repository;
use Filament\Infolists\Components\KeyValueEntry;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Elasticsearch';

    protected static ?string $recordTitleAttribute = 'id';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('id'),
                TextEntry::make('type')->badge(),
                KeyValueEntry::make('settings'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('type')->searchable()->badge(),
                TextColumn::make('settings')
                    ->searchable(),
            ])
            ->filters([])
            ->recordUrl(fn(Repository $record) => static::getUrl('view', ['record' => $record]))
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
            'index' => ListRepositories::route('/'),
            'view' => ViewRepository::route('/{record}'),
        ];
    }
}
