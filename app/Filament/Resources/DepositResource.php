<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ClientStatus;
use App\Enums\DepositStatus;
use App\Filament\Resources\DepositResource\Pages;
use App\Models\Client;
use App\Models\Deposit;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only ресурс: депозиты создаются только синхронизацией.
 */
class DepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Desk';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'tx_hash';

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
                Tables\Columns\TextColumn::make('block_timestamp')
                    ->label('Block time')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount, USDT')
                    ->alignEnd()
                    ->weight('medium')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('wallet.client.name')
                    ->label('Client')
                    ->sortable()
                    ->badge()
                    ->color(fn (Deposit $record): string => $record->wallet->client->status === ClientStatus::Blocked ? 'danger' : 'gray')
                    ->icon(fn (Deposit $record): ?string => $record->wallet->client->status === ClientStatus::Blocked ? 'heroicon-m-no-symbol' : null)
                    ->tooltip(fn (Deposit $record): ?string => $record->wallet->client->status === ClientStatus::Blocked ? 'Client is blocked' : null),

                Tables\Columns\TextColumn::make('wallet.address')
                    ->label('Wallet')
                    ->searchable()
                    ->copyable()
                    ->limit(18),

                Tables\Columns\TextColumn::make('from_address')
                    ->label('From')
                    ->searchable()
                    ->copyable()
                    ->limit(18)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tx_hash')
                    ->label('Tx hash')
                    ->searchable()
                    ->copyable()
                    ->limit(16)
                    ->url(fn (Deposit $record): string => $record->explorerUrl())
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition('after')
                    ->tooltip('Открыть в TronScan'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DepositStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('client')
                    ->label('Client')
                    ->options(fn (): array => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $clientId): Builder => $query->whereHas(
                            'wallet',
                            fn (Builder $wallet): Builder => $wallet->where('client_id', $clientId),
                        ),
                    ))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('wallet_id')
                    ->label('Wallet')
                    ->relationship('wallet', 'address')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('block_timestamp')
                    ->label('Block time')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('block_timestamp', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('block_timestamp', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.$data['from'];
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('explorer')
                    ->label('TronScan')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Deposit $record): string => $record->explorerUrl())
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('block_timestamp', 'desc')
            ->emptyStateHeading('Депозитов пока нет')
            ->emptyStateDescription('Запустите синхронизацию кошелька: кнопка «Sync now» или php artisan deposits:sync.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Transfer')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('amount')->label('Amount, USDT')->weight('bold'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('token_symbol')->label('Token'),
                    Infolists\Components\TextEntry::make('tx_hash')
                        ->label('Tx hash')
                        ->copyable()
                        ->url(fn (Deposit $record): string => $record->explorerUrl())
                        ->openUrlInNewTab()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('from_address')->label('From')->copyable(),
                    Infolists\Components\TextEntry::make('to_address')->label('To')->copyable(),
                    Infolists\Components\TextEntry::make('block_timestamp')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                    Infolists\Components\TextEntry::make('contract_address')->label('Contract')->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')->label('Imported at')->dateTime('Y-m-d H:i:s'),
                ]),

            Infolists\Components\Section::make('Client & wallet')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('wallet.client.name')->label('Client'),
                    Infolists\Components\TextEntry::make('wallet.client.status')->label('Client status')->badge(),
                    Infolists\Components\TextEntry::make('wallet.address')->label('Wallet')->copyable(),
                ]),

            Infolists\Components\Section::make('Raw TronScan payload')
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('raw_payload')
                        ->hiddenLabel()
                        ->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—')
                        ->columnSpanFull()
                        ->fontFamily('mono')
                        ->size('xs'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeposits::route('/'),
            'view' => Pages\ViewDeposit::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['wallet.client']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->where('status', DepositStatus::Pending)->count() ?: null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending deposits';
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['tx_hash', 'from_address'];
    }
}
