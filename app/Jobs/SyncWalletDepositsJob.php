<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Deposits\DepositSyncService;
use App\Domain\Deposits\Exceptions\WalletNotSyncableException;
use App\Enums\SyncTrigger;
use App\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Тонкая обёртка: вся логика — в DepositSyncService.
 *
 * ShouldBeUnique не даёт нескольким прогонам по одному кошельку идти параллельно
 * (schedule каждые 5 минут + ручной «Sync now» из админки).
 */
class SyncWalletDepositsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Ретраи на сетевые сбои/429 TronScan. */
    public int $tries = 3;

    /** Экспоненциальная пауза между попытками, сек. */
    public array $backoff = [10, 30, 60];

    /** Максимальное время выполнения одной попытки, сек. */
    public int $timeout = 120;

    /** Замок уникальности живёт не дольше 10 минут. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $walletId,
        public readonly SyncTrigger $trigger = SyncTrigger::Manual,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->walletId;
    }

    public function handle(DepositSyncService $service): void
    {
        $wallet = Wallet::query()->find($this->walletId);

        if ($wallet === null) {
            Log::warning('SyncWalletDepositsJob: wallet not found, skipping.', ['wallet_id' => $this->walletId]);

            return;
        }

        try {
            $service->syncWallet($wallet, $this->trigger);
        } catch (WalletNotSyncableException $e) {
            // Бизнес-правило, а не сбой — ретраить бессмысленно.
            Log::info($e->getMessage());

            $this->fail($e);
        }
    }
}
