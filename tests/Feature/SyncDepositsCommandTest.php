<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SyncTrigger;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\SyncRun;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTronScan;
use Tests\TestCase;

class SyncDepositsCommandTest extends TestCase
{
    use InteractsWithTronScan;
    use RefreshDatabase;

    #[Test]
    public function it_syncs_all_active_wallets(): void
    {
        $this->fakeTronScan();

        Wallet::factory()->for(Client::factory())->create(['address' => $this->fixtureWalletAddress]);

        $this->artisan('deposits:sync')
            ->expectsOutputToContain('Sync finished.')
            ->assertSuccessful();

        $this->assertSame(4, Deposit::query()->count());
        $this->assertNull(SyncRun::query()->latest('id')->first()?->wallet_id);
    }

    #[Test]
    public function it_syncs_a_single_wallet_by_id_and_by_address(): void
    {
        $this->fakeTronScan();

        $wallet = Wallet::factory()->for(Client::factory())->create(['address' => $this->fixtureWalletAddress]);

        $this->artisan('deposits:sync', ['wallet' => (string) $wallet->id])->assertSuccessful();
        $this->assertSame(4, Deposit::query()->count());

        // Повторный прогон по адресу — дублей не создаёт.
        $this->artisan('deposits:sync', ['wallet' => $wallet->address])->assertSuccessful();
        $this->assertSame(4, Deposit::query()->count());
        $this->assertSame(2, SyncRun::query()->where('wallet_id', $wallet->id)->count());
    }

    #[Test]
    public function it_fails_for_an_unknown_wallet(): void
    {
        $this->artisan('deposits:sync', ['wallet' => '999'])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    #[Test]
    public function the_queue_flag_dispatches_a_job_per_active_wallet(): void
    {
        Queue::fake();

        $active = Wallet::factory()->count(2)->for(Client::factory())->create();
        Wallet::factory()->for(Client::factory())->inactive()->create();

        $this->artisan('deposits:sync', ['--queue' => true])->assertSuccessful();

        Queue::assertPushed(SyncWalletDepositsJob::class, 2);

        Queue::assertPushed(
            SyncWalletDepositsJob::class,
            fn (SyncWalletDepositsJob $job): bool => $job->walletId === $active->first()->id
                && $job->trigger === SyncTrigger::Command,
        );
    }

    #[Test]
    public function the_schedule_trigger_is_recorded_in_sync_runs(): void
    {
        Queue::fake();

        Wallet::factory()->for(Client::factory())->create();

        $this->artisan('deposits:sync', ['--queue' => true, '--trigger' => 'schedule'])->assertSuccessful();

        Queue::assertPushed(
            SyncWalletDepositsJob::class,
            fn (SyncWalletDepositsJob $job): bool => $job->trigger === SyncTrigger::Schedule,
        );
    }

    #[Test]
    public function the_job_delegates_to_the_service(): void
    {
        $this->fakeTronScan();

        $wallet = Wallet::factory()->for(Client::factory())->create(['address' => $this->fixtureWalletAddress]);

        // QUEUE_CONNECTION=sync в тестах — Job выполняется тут же.
        SyncWalletDepositsJob::dispatch($wallet->id, SyncTrigger::Manual);

        $this->assertSame(4, Deposit::query()->where('wallet_id', $wallet->id)->count());
        $this->assertSame(SyncTrigger::Manual, SyncRun::query()->latest('id')->firstOrFail()->trigger);
    }
}
