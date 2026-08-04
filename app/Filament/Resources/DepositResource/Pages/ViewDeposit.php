<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositResource\Pages;

use App\Filament\Resources\DepositResource;
use App\Models\Deposit;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeposit extends ViewRecord
{
    protected static string $resource = DepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('explorer')
                ->label('Открыть в TronScan')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (Deposit $record): string => $record->explorerUrl())
                ->openUrlInNewTab(),
        ];
    }
}
