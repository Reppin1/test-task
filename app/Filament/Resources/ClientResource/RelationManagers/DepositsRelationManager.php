<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only лента депозитов клиента (hasManyThrough clients → wallets → deposits).
 */
class DepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'deposits';

    protected static ?string $title = 'Deposits';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tx_hash')
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
                    ->badge(),

                Tables\Columns\TextColumn::make('wallet.address')
                    ->label('Wallet')
                    ->limit(16)
                    ->copyable(),

                Tables\Columns\TextColumn::make('tx_hash')
                    ->label('Tx')
                    ->limit(14)
                    ->copyable()
                    ->url(fn (Deposit $record): string => $record->explorerUrl())
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition('after'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DepositStatus::class),
            ])
            ->defaultSort('block_timestamp', 'desc')
            ->paginated([10, 25, 50]);
    }
}
