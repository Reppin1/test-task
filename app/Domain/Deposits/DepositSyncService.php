<?php

declare(strict_types=1);

namespace App\Domain\Deposits;

use App\Domain\Deposits\Exceptions\WalletNotSyncableException;
use App\Domain\TronScan\Contracts\TronScanClient;
use App\Domain\TronScan\DTO\Trc20Transfer;
use App\Enums\DepositStatus;
use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\Deposit;
use App\Models\SyncRun;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Вся логика синхронизации депозитов. Filament-ресурсы, команда и Job —
 * только тонкие обёртки над этим сервисом.
 *
 * Гарантии:
 *  - создаём депозиты только для входящих переводов (to_address == wallet.address);
 *  - идемпотентность по deposits.tx_hash (проверка + перехват unique-violation на гонке);
 *  - каждый прогон пишется в sync_runs, кошельку обновляется last_synced_at.
 */
final class DepositSyncService
{
    public function __construct(
        private readonly TronScanClient $client,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Синхронизация одного кошелька. Пишет отдельный sync_run (wallet_id = id).
     *
     * @throws Throwable
     */
    public function syncWallet(Wallet $wallet, SyncTrigger $trigger = SyncTrigger::Manual): SyncResult
    {
        $this->assertSyncable($wallet, batch: false);

        $run = $this->startRun($trigger, $wallet);

        try {
            $result = $this->pull($wallet);
        } catch (Throwable $e) {
            $this->failRun($run, $e);

            throw $e;
        }

        $this->finishRun($run, $result);

        return $result->withRunId($run->id);
    }

    /**
     * Batch по всем активным кошелькам. Пишет один sync_run с wallet_id = null.
     * Падение одного кошелька не останавливает остальные.
     */
    public function syncActiveWallets(SyncTrigger $trigger = SyncTrigger::Schedule): SyncResult
    {
        $run = $this->startRun($trigger, null);

        /** @var Collection<int, Wallet> $wallets */
        $wallets = Wallet::query()->active()->orderBy('id')->get();

        $result = new SyncResult;
        $errors = [];

        foreach ($wallets as $wallet) {
            try {
                $result = $result->plus($this->pull($wallet));
            } catch (Throwable $e) {
                $errors[] = sprintf('wallet #%d (%s): %s', $wallet->id, $wallet->address, $e->getMessage());

                $this->logger->error('Wallet sync failed during batch run.', [
                    'wallet_id' => $wallet->id,
                    'sync_run_id' => $run->id,
                    'exception' => $e,
                ]);
            }
        }

        if ($errors !== []) {
            $result = $result->withError(implode(PHP_EOL, $errors));
            $this->finishRun($run, $result, SyncStatus::Failed);
        } else {
            $this->finishRun($run, $result);
        }

        return $result->withRunId($run->id);
    }

    /**
     * Тянет трансферы постранично и складывает входящие в deposits.
     * Сам по себе sync_run не создаёт — этим управляют методы выше.
     */
    private function pull(Wallet $wallet): SyncResult
    {
        $pageSize = (int) config('tronscan.page_size', 50);
        $maxPages = max(1, (int) config('tronscan.max_pages', 5));
        $contract = (string) config('tronscan.usdt_contract');

        $result = new SyncResult;

        for ($page = 0; $page < $maxPages; $page++) {
            $transferPage = $this->client->transfers(
                address: $wallet->address,
                limit: $pageSize,
                start: $page * $pageSize,
                contractAddress: $contract,
            );

            foreach ($transferPage->transfers as $transfer) {
                $result = $result->plus($this->store($wallet, $transfer, $contract));
            }

            if (! $transferPage->hasMore()) {
                break;
            }
        }

        $wallet->forceFill(['last_synced_at' => Carbon::now()])->save();

        return $result;
    }

    /**
     * Один трансфер → максимум одна строка deposits.
     */
    private function store(Wallet $wallet, Trc20Transfer $transfer, string $contract): SyncResult
    {
        // Только входящие на адрес кошелька и только нужный контракт.
        if (! $transfer->isIncomingTo($wallet->address)) {
            return new SyncResult(fetched: 1, skipped: 1);
        }

        if ($transfer->contractAddress !== null && $transfer->contractAddress !== $contract) {
            return new SyncResult(fetched: 1, skipped: 1);
        }

        if ($transfer->txHash === '') {
            return new SyncResult(fetched: 1, skipped: 1);
        }

        // Быстрый путь идемпотентности: уже видели этот tx.
        if (Deposit::query()->where('tx_hash', $transfer->txHash)->exists()) {
            return new SyncResult(fetched: 1, skipped: 1);
        }

        $status = $this->resolveStatus($transfer);

        try {
            Deposit::query()->create([
                'wallet_id' => $wallet->id,
                'tx_hash' => $transfer->txHash,
                'from_address' => $transfer->fromAddress,
                'to_address' => $transfer->toAddress,
                'amount' => $transfer->amount,
                'token_symbol' => $transfer->tokenSymbol,
                'contract_address' => $transfer->contractAddress,
                'block_timestamp' => $transfer->blockTimestamp,
                'status' => $status,
                'raw_payload' => $transfer->raw,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Гонка двух воркеров по одному кошельку — строка уже создана соседом.
            return new SyncResult(fetched: 1, skipped: 1);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return new SyncResult(fetched: 1, skipped: 1);
            }

            throw $e;
        }

        return new SyncResult(
            fetched: 1,
            created: 1,
            ignored: $status === DepositStatus::Ignored ? 1 : 0,
        );
    }

    /**
     * amount <= 0 → ignored (строку создаём, чтобы остался аудит-след);
     * неподтверждённая транзакция → pending; иначе confirmed.
     */
    private function resolveStatus(Trc20Transfer $transfer): DepositStatus
    {
        if (! $transfer->isPositive()) {
            return DepositStatus::Ignored;
        }

        return $transfer->confirmed ? DepositStatus::Confirmed : DepositStatus::Pending;
    }

    private function assertSyncable(Wallet $wallet, bool $batch): void
    {
        if ($wallet->is_active) {
            return;
        }

        if ($batch || ! config('tronscan.manual_sync_for_inactive_wallets', true)) {
            throw WalletNotSyncableException::inactive($wallet);
        }
    }

    private function startRun(SyncTrigger $trigger, ?Wallet $wallet): SyncRun
    {
        return SyncRun::query()->create([
            'wallet_id' => $wallet?->id,
            'trigger' => $trigger,
            'status' => SyncStatus::Running,
            'started_at' => Carbon::now(),
        ]);
    }

    private function finishRun(SyncRun $run, SyncResult $result, SyncStatus $status = SyncStatus::Success): void
    {
        $run->forceFill([
            'status' => $status,
            'fetched_count' => $result->fetched,
            'created_count' => $result->created,
            'error_message' => $result->error,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    private function failRun(SyncRun $run, Throwable $e): void
    {
        $this->logger->error('Wallet sync failed.', [
            'sync_run_id' => $run->id,
            'wallet_id' => $run->wallet_id,
            'exception' => $e,
        ]);

        $run->forceFill([
            'status' => SyncStatus::Failed,
            'error_message' => mb_substr($e->getMessage(), 0, 2000),
            'finished_at' => Carbon::now(),
        ])->save();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[1] ?? ''), ['1062', '19'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
