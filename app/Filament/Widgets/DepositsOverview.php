<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\DepositStatus;
use App\Enums\SyncStatus;
use App\Models\Deposit;
use App\Models\SyncRun;
use App\Models\Wallet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DepositsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $lastRun = SyncRun::query()
            ->where('status', SyncStatus::Success)
            ->latest('finished_at')
            ->first();

        return [
            Stat::make('Confirmed USDT', $this->confirmedTotal())
                ->description('Сумма подтверждённых депозитов')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Deposits', (string) Deposit::query()->count())
                ->description(sprintf('%d pending', Deposit::query()->where('status', DepositStatus::Pending)->count()))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),

            Stat::make('Active wallets', (string) Wallet::query()->active()->count())
                ->description(sprintf('%d всего', Wallet::query()->count()))
                ->descriptionIcon('heroicon-m-wallet')
                ->color('gray'),

            Stat::make('Last successful sync', $lastRun?->finished_at?->diffForHumans() ?? 'никогда')
                ->description($lastRun === null ? 'Запустите deposits:sync' : sprintf('run #%d, +%d deposits', $lastRun->id, $lastRun->created_count))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($lastRun === null ? 'warning' : 'info'),
        ];
    }

    /**
     * SUM считаем на стороне БД и показываем строкой — деньги во float не гоняем.
     */
    private function confirmedTotal(): string
    {
        $total = DB::table('deposits')
            ->where('status', DepositStatus::Confirmed->value)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        return rtrim(rtrim((string) $total, '0'), '.') ?: '0';
    }
}
