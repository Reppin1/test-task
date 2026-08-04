<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Deposits\DepositSyncService;
use App\Domain\TronScan\Contracts\TronScanClient;
use App\Domain\TronScan\FakeTronScanClient;
use App\Enums\SyncTrigger;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Демо-режим TRONSCAN_DRIVER=fake: депозиты появляются без ключа и без сети.
 */
class FakeTronScanDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tronscan.driver', 'fake');

        // Ни одного реального HTTP-запроса не должно случиться.
        Http::preventStrayRequests();
        Http::fake();
    }

    #[Test]
    public function the_container_resolves_the_fake_driver(): void
    {
        $this->assertInstanceOf(FakeTronScanClient::class, app(TronScanClient::class));
    }

    #[Test]
    public function it_creates_deposits_from_the_fixture_for_any_wallet(): void
    {
        $wallet = Wallet::factory()->for(Client::factory())->create();

        $result = app(DepositSyncService::class)->syncWallet($wallet, SyncTrigger::Manual);

        $this->assertSame(4, $result->created);
        $this->assertSame(4, Deposit::query()->where('wallet_id', $wallet->id)->count());

        // Все входящие переписаны на адрес запрошенного кошелька.
        $this->assertSame(
            4,
            Deposit::query()->where('to_address', $wallet->address)->count(),
        );

        Http::assertNothingSent();
    }

    #[Test]
    public function two_wallets_do_not_collide_on_the_tx_hash_unique_index(): void
    {
        $walletA = Wallet::factory()->for(Client::factory())->create();
        $walletB = Wallet::factory()->for(Client::factory())->create();

        $service = app(DepositSyncService::class);

        $service->syncWallet($walletA, SyncTrigger::Manual);
        $service->syncWallet($walletB, SyncTrigger::Manual);

        $this->assertSame(4, Deposit::query()->where('wallet_id', $walletA->id)->count());
        $this->assertSame(4, Deposit::query()->where('wallet_id', $walletB->id)->count());
        $this->assertSame(8, Deposit::query()->distinct()->count('tx_hash'));
    }
}
