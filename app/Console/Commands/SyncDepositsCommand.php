<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Deposits\DepositSyncService;
use App\Domain\Deposits\SyncResult;
use App\Enums\SyncTrigger;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * php artisan deposits:sync            — все активные кошельки, синхронно
 * php artisan deposits:sync 5          — кошелёк по id (или по TRON-адресу)
 * php artisan deposits:sync --queue    — по Job на каждый активный кошелёк
 * php artisan deposits:sync 5 --queue  — Job на один кошелёк
 */
class SyncDepositsCommand extends Command
{
    protected $signature = 'deposits:sync
                            {wallet? : ID кошелька или TRON-адрес; без аргумента — все активные}
                            {--queue : Не синхронизировать в процессе команды, а поставить Job(ы) в очередь}
                            {--trigger=command : Метка источника прогона для sync_runs (command|schedule|manual)}';

    protected $description = 'Подтянуть входящие USDT TRC-20 переводы с TronScan в таблицу deposits';

    public function handle(DepositSyncService $service): int
    {
        $trigger = SyncTrigger::tryFrom((string) $this->option('trigger')) ?? SyncTrigger::Command;
        $walletKey = $this->argument('wallet');

        if ($walletKey !== null) {
            $wallet = $this->resolveWallet((string) $walletKey);

            if ($wallet === null) {
                $this->error(sprintf('Wallet "%s" not found.', $walletKey));

                return self::FAILURE;
            }

            return $this->option('queue')
                ? $this->queueWallets(collect([$wallet]), $trigger)
                : $this->runSync(fn (): SyncResult => $service->syncWallet($wallet, $trigger));
        }

        if ($this->option('queue')) {
            return $this->queueWallets(Wallet::query()->active()->orderBy('id')->get(), $trigger);
        }

        return $this->runSync(fn (): SyncResult => $service->syncActiveWallets($trigger));
    }

    private function resolveWallet(string $key): ?Wallet
    {
        return Wallet::query()
            ->when(
                ctype_digit($key),
                fn ($query) => $query->whereKey((int) $key),
                fn ($query) => $query->where('address', $key),
            )
            ->first();
    }

    /**
     * @param  Collection<int, Wallet>  $wallets
     */
    private function queueWallets($wallets, SyncTrigger $trigger): int
    {
        if ($wallets->isEmpty()) {
            $this->warn('No active wallets to sync.');

            return self::SUCCESS;
        }

        foreach ($wallets as $wallet) {
            SyncWalletDepositsJob::dispatch($wallet->id, $trigger);
        }

        $this->info(sprintf('Queued %d sync job(s). Run `php artisan queue:work` to process them.', $wallets->count()));

        return self::SUCCESS;
    }

    /**
     * @param  callable(): SyncResult  $callback
     */
    private function runSync(callable $callback): int
    {
        try {
            $result = $callback();
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['fetched', 'created', 'ignored', 'skipped', 'sync_run'],
            [[$result->fetched, $result->created, $result->ignored, $result->skipped, $result->runId]],
        );

        if ($result->failed()) {
            $this->error('Finished with errors:'.PHP_EOL.$result->error);

            return self::FAILURE;
        }

        $this->info('Sync finished.');

        return self::SUCCESS;
    }
}
