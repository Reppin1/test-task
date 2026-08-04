<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Filament\Resources\SyncRunResource\Pages;
use App\Models\SyncRun;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Журнал прогонов синхронизации — только чтение.
 */
class SyncRunResource extends Resource
{
    protected static ?string $model = SyncRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Sync runs';

    protected static ?string $modelLabel = 'sync run';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('trigger')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('wallet.address')
                    ->label('Wallet')
                    ->limit(18)
                    ->placeholder('all active (batch)')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fetched_count')
                    ->label('Fetched')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_count')
                    ->label('Created')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration, s')
                    ->alignEnd()
                    ->state(fn (SyncRun $record): string => $record->durationInSeconds() === null ? '—' : (string) $record->durationInSeconds()),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(30)
                    ->placeholder('—')
                    ->color('danger')
                    ->tooltip(fn (?string $state): ?string => $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(SyncStatus::class)->multiple(),
                Tables\Filters\SelectFilter::make('trigger')->options(SyncTrigger::class)->multiple(),
                Tables\Filters\Filter::make('batch_only')
                    ->label('Batch runs only')
                    ->query(fn (Builder $query): Builder => $query->whereNull('wallet_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('with_new_deposits')
                    ->label('With new deposits')
                    ->query(fn (Builder $query): Builder => $query->where('created_count', '>', 0))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('30s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('trigger')->badge(),
                    Infolists\Components\TextEntry::make('wallet.address')->label('Wallet')->placeholder('all active (batch)'),
                    Infolists\Components\TextEntry::make('fetched_count')->label('Fetched'),
                    Infolists\Components\TextEntry::make('created_count')->label('Created'),
                    Infolists\Components\TextEntry::make('started_at')->dateTime('Y-m-d H:i:s'),
                    Infolists\Components\TextEntry::make('finished_at')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                    Infolists\Components\TextEntry::make('error_message')
                        ->label('Error')
                        ->placeholder('—')
                        ->color('danger')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncRuns::route('/'),
            'view' => Pages\ViewSyncRun::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('wallet');
    }
}
