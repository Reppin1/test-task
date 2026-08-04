<?php

declare(strict_types=1);

namespace App\Filament\Resources\WalletResource\Pages;

use App\Enums\SyncTrigger;
use App\Filament\Resources\WalletResource;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Wallet;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWallet extends ViewRecord
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('Sync now')
                ->icon('heroicon-m-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (Wallet $record): bool => $record->is_active || (bool) config('tronscan.manual_sync_for_inactive_wallets'))
                ->action(function (Wallet $record): void {
                    SyncWalletDepositsJob::dispatch($record->id, SyncTrigger::Manual);

                    Notification::make()->title('Sync queued')->body($record->address)->success()->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}
