<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Deposits\DepositSyncService;
use App\Domain\Deposits\Exceptions\WalletNotSyncableException;
use App\Domain\TronScan\Exceptions\TronScanRateLimitException;
use App\Domain\TronScan\Exceptions\TronScanRequestException;
use App\Enums\DepositStatus;
use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\SyncRun;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTronScan;
use Tests\TestCase;

class DepositSyncServiceTest extends TestCase
{
    use InteractsWithTronScan;
    use RefreshDatabase;

    private function wallet(bool $active = true): Wallet
    {
        return Wallet::factory()
            ->for(Client::factory())
            ->create([
                'address' => $this->fixtureWalletAddress,
                'is_active' => $active,
            ]);
    }

    private function service(): DepositSyncService
    {
        return app(DepositSyncService::class);
    }

    #[Test]
    public function it_maps_the_tronscan_fixture_into_deposits(): void
    {
        $this->fakeTronScan();

        $wallet = $this->wallet();

        $result = $this->service()->syncWallet($wallet, SyncTrigger::Command);

        // В фикстуре 5 трансферов: 4 входящих (один из них — нулевой) и 1 исходящий.
        $this->assertSame(5, $result->fetched);
        $this->assertSame(4, $result->created);
        $this->assertSame(1, $result->ignored);
        $this->assertSame(1, $result->skipped);

        $this->assertSame(4, Deposit::query()->count());

        $deposit = Deposit::query()->where('tx_hash', '8f2c0a5f4b3e4d1a9c7b6e5d4c3b2a1908f7e6d5c4b3a29180f7e6d5c4b3a291')->firstOrFail();

        $this->assertSame('12.500000', $deposit->amount);
        $this->assertSame(DepositStatus::Confirmed, $deposit->status);
        $this->assertSame('TJDENsfBJs4RFETt1X1W8wMDc8M5XnJhCe', $deposit->from_address);
        $this->assertSame($wallet->address, $deposit->to_address);
        $this->assertSame('USDT', $deposit->token_symbol);
        $this->assertSame('2025-07-03 00:00:00', $deposit->block_timestamp?->format('Y-m-d H:i:s'));
        $this->assertIsArray($deposit->raw_payload);
    }

    #[Test]
    public function it_skips_outgoing_transfers(): void
    {
        $this->fakeTronScan();

        $wallet = $this->wallet();

        $this->service()->syncWallet($wallet, SyncTrigger::Command);

        // Исходящий перевод из фикстуры не должен попасть в депозиты.
        $this->assertDatabaseMissing('deposits', [
            'tx_hash' => 'ff00ee11dd22cc33bb44aa5599668877ff00ee11dd22cc33bb44aa5599668877',
        ]);
    }

    #[Test]
    public function zero_amount_transfers_are_stored_as_ignored(): void
    {
        $this->fakeTronScan();

        $this->service()->syncWallet($this->wallet(), SyncTrigger::Command);

        $deposit = Deposit::query()->where('tx_hash', '0d0c0b0a090807060504030201f0e0d0c0b0a0908070605040302010fedcba98')->firstOrFail();

        $this->assertSame(DepositStatus::Ignored, $deposit->status);
        $this->assertSame('0.000000', $deposit->amount);
    }

    #[Test]
    public function unconfirmed_transfers_are_stored_as_pending(): void
    {
        $this->fakeTronScan();

        $this->service()->syncWallet($this->wallet(), SyncTrigger::Command);

        $deposit = Deposit::query()->where('tx_hash', 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789')->firstOrFail();

        $this->assertSame(DepositStatus::Pending, $deposit->status);
    }

    #[Test]
    public function a_second_sync_creates_no_duplicates(): void
    {
        $this->fakeTronScan();

        $wallet = $this->wallet();

        $first = $this->service()->syncWallet($wallet, SyncTrigger::Command);
        $second = $this->service()->syncWallet($wallet->refresh(), SyncTrigger::Command);

        $this->assertSame(4, $first->created);
        $this->assertSame(0, $second->created);
        $this->assertSame(5, $second->skipped);

        $this->assertSame(4, Deposit::query()->count());
        $this->assertSame(2, SyncRun::query()->count());
    }

    #[Test]
    public function it_writes_a_sync_run_and_touches_the_wallet(): void
    {
        $this->fakeTronScan();

        $wallet = $this->wallet();
        $this->assertNull($wallet->last_synced_at);

        $result = $this->service()->syncWallet($wallet, SyncTrigger::Manual);

        $run = SyncRun::query()->findOrFail($result->runId);

        $this->assertSame($wallet->id, $run->wallet_id);
        $this->assertSame(SyncStatus::Success, $run->status);
        $this->assertSame(SyncTrigger::Manual, $run->trigger);
        $this->assertSame(5, $run->fetched_count);
        $this->assertSame(4, $run->created_count);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->error_message);

        $this->assertNotNull($wallet->refresh()->last_synced_at);
    }

    #[Test]
    public function batch_sync_covers_only_active_wallets_and_writes_one_run(): void
    {
        $this->fakeTronScan($this->emptyPayload());

        $active = $this->wallet();
        $inactive = Wallet::factory()->for(Client::factory())->inactive()->create();

        $result = $this->service()->syncActiveWallets(SyncTrigger::Schedule);

        $run = SyncRun::query()->findOrFail($result->runId);

        $this->assertNull($run->wallet_id, 'batch-прогон пишется с wallet_id = null');
        $this->assertSame(SyncStatus::Success, $run->status);

        $this->assertNotNull($active->refresh()->last_synced_at);
        $this->assertNull($inactive->refresh()->last_synced_at);
    }

    #[Test]
    public function a_failed_run_is_recorded_and_the_exception_bubbles_up(): void
    {
        $this->fakeTronScanStatus(429, 'rate limit');

        $wallet = $this->wallet();

        try {
            $this->service()->syncWallet($wallet, SyncTrigger::Command);
            $this->fail('TronScanRateLimitException was expected.');
        } catch (TronScanRateLimitException $e) {
            $this->assertStringContainsString('rate limit', strtolower($e->getMessage()));
        }

        $run = SyncRun::query()->latest('id')->firstOrFail();

        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertNotNull($run->error_message);
        $this->assertNull($wallet->refresh()->last_synced_at);
    }

    #[Test]
    public function client_errors_are_mapped_to_a_domain_exception(): void
    {
        $this->fakeTronScanStatus(400, 'bad address');

        $this->expectException(TronScanRequestException::class);

        $this->service()->syncWallet($this->wallet(), SyncTrigger::Command);
    }

    #[Test]
    public function deposits_of_a_blocked_client_are_still_stored(): void
    {
        $this->fakeTronScan();

        $wallet = Wallet::factory()
            ->for(Client::factory()->blocked())
            ->create(['address' => $this->fixtureWalletAddress]);

        $this->service()->syncWallet($wallet, SyncTrigger::Command);

        $this->assertSame(4, Deposit::query()->where('wallet_id', $wallet->id)->count());
    }

    #[Test]
    public function manual_sync_of_an_inactive_wallet_can_be_disabled_by_config(): void
    {
        $this->fakeTronScan();

        config()->set('tronscan.manual_sync_for_inactive_wallets', false);

        $this->expectException(WalletNotSyncableException::class);

        $this->service()->syncWallet($this->wallet(active: false), SyncTrigger::Manual);
    }

    #[Test]
    public function the_api_key_is_sent_as_a_header(): void
    {
        $this->fakeTronScan();

        $this->service()->syncWallet($this->wallet(), SyncTrigger::Command);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('TRON-PRO-API-KEY', 'test-api-key')
                && str_contains($request->url(), 'relatedAddress='.$this->fixtureWalletAddress)
                && str_contains($request->url(), 'contract_address=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
        });
    }
}
