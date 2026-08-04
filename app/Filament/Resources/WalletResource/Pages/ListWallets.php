<?php

declare(strict_types=1);

namespace App\Filament\Resources\WalletResource\Pages;

use App\Enums\SyncTrigger;
use App\Filament\Resources\WalletResource;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Wallet;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWallets extends ListRecords
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncAll')
                ->label('Sync all active')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $wallets = Wallet::query()->active()->pluck('id');

                    $wallets->each(fn (int $id) => SyncWalletDepositsJob::dispatch($id, SyncTrigger::Manual));

                    Notification::make()
                        ->title(sprintf('Queued %d sync job(s)', $wallets->count()))
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
