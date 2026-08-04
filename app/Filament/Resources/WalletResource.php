<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ClientStatus;
use App\Enums\SyncTrigger;
use App\Filament\Resources\WalletResource\Pages;
use App\Filament\Resources\WalletResource\RelationManagers;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Wallet;
use App\Rules\TronAddressRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Desk';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'address';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Wallet')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('address')
                        ->label('TRON address')
                        ->required()
                        ->maxLength(64)
                        ->rules([new TronAddressRule])
                        ->unique(ignoreRecord: true)
                        ->helperText('base58 TRC-20 адрес: «T» + 33 символа, проверяется checksum.'),

                    Forms\Components\TextInput::make('label')
                        ->maxLength(255)
                        ->placeholder('Hot wallet #1'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->helperText('Неактивные кошельки не участвуют в schedule/batch синхронизации.'),

                    Forms\Components\Placeholder::make('last_synced_at')
                        ->label('Last synced at')
                        ->content(fn (?Wallet $record): string => $record?->last_synced_at?->format('Y-m-d H:i:s') ?? 'never'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->copyable()
                    ->limit(22)
                    ->weight('medium')
                    ->description(fn (Wallet $record): ?string => $record->label),

                Tables\Columns\TextColumn::make('client.name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Wallet $record): string => $record->client->email)
                    ->badge()
                    ->color(fn (Wallet $record): string => $record->client->status === ClientStatus::Blocked ? 'danger' : 'gray')
                    ->icon(fn (Wallet $record): ?string => $record->client->status === ClientStatus::Blocked ? 'heroicon-m-no-symbol' : null)
                    ->tooltip(fn (Wallet $record): ?string => $record->client->status === ClientStatus::Blocked ? 'Client is blocked' : null),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('deposits_count')
                    ->counts('deposits')
                    ->label('Deposits')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last sync')
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->placeholder('never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('never_synced')
                    ->label('Never synced')
                    ->query(fn (Builder $query): Builder => $query->whereNull('last_synced_at'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync now')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Поставим задачу в очередь: TronScan будет опрошен воркером, UI не блокируется.')
                    ->visible(fn (Wallet $record): bool => $record->is_active || (bool) config('tronscan.manual_sync_for_inactive_wallets'))
                    ->action(function (Wallet $record): void {
                        SyncWalletDepositsJob::dispatch($record->id, SyncTrigger::Manual);

                        Notification::make()
                            ->title('Sync queued')
                            ->body($record->address)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('sync')
                        ->label('Sync selected')
                        ->icon('heroicon-m-arrow-path')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            /** @var Wallet $wallet */
                            foreach ($records as $wallet) {
                                SyncWalletDepositsJob::dispatch($wallet->id, SyncTrigger::Manual);
                            }

                            Notification::make()
                                ->title(sprintf('Queued %d sync job(s)', $records->count()))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DepositsRelationManager::class,
            RelationManagers\SyncRunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWallets::route('/'),
            'create' => Pages\CreateWallet::route('/create'),
            'view' => Pages\ViewWallet::route('/{record}'),
            'edit' => Pages\EditWallet::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('client');
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['address', 'label'];
    }
}
