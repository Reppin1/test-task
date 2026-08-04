<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SyncTrigger;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\DepositResource;
use App\Filament\Resources\SyncRunResource;
use App\Filament\Resources\WalletResource;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilamentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get('/admin/clients')->assertRedirect('/admin/login');
    }

    #[Test]
    public function admin_pages_render(): void
    {
        $client = Client::factory()->create();
        $wallet = Wallet::factory()->for($client)->create();
        Deposit::factory()->forWallet($wallet)->create();

        $this->get('/admin')->assertOk();
        $this->get(ClientResource::getUrl('index'))->assertOk();
        $this->get(WalletResource::getUrl('index'))->assertOk();
        $this->get(DepositResource::getUrl('index'))->assertOk();
        $this->get(SyncRunResource::getUrl('index'))->assertOk();
    }

    #[Test]
    public function the_deposits_table_lists_records_and_filters_by_status(): void
    {
        $wallet = Wallet::factory()->for(Client::factory())->create();

        $confirmed = Deposit::factory()->forWallet($wallet)->create();
        $pending = Deposit::factory()->forWallet($wallet)->pending()->create();

        Livewire::test(DepositResource\Pages\ListDeposits::class)
            ->assertCanSeeTableRecords([$confirmed, $pending])
            ->filterTable('status', ['pending'])
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$confirmed]);
    }

    #[Test]
    public function the_sync_now_action_dispatches_a_job(): void
    {
        Queue::fake();

        $wallet = Wallet::factory()->for(Client::factory())->create();

        Livewire::test(WalletResource\Pages\ListWallets::class)
            ->callTableAction('sync', $wallet)
            ->assertHasNoTableActionErrors();

        Queue::assertPushed(
            SyncWalletDepositsJob::class,
            fn (SyncWalletDepositsJob $job): bool => $job->walletId === $wallet->id
                && $job->trigger === SyncTrigger::Manual,
        );
    }

    #[Test]
    public function a_wallet_with_an_invalid_tron_address_is_rejected(): void
    {
        $client = Client::factory()->create();

        Livewire::test(WalletResource\Pages\CreateWallet::class)
            ->fillForm([
                'client_id' => $client->id,
                'address' => 'TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiY', // битый checksum
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['address']);

        $this->assertSame(0, Wallet::query()->count());
    }

    #[Test]
    public function a_wallet_with_a_valid_tron_address_is_created(): void
    {
        $client = Client::factory()->create();

        Livewire::test(WalletResource\Pages\CreateWallet::class)
            ->fillForm([
                'client_id' => $client->id,
                'address' => 'TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX',
                'label' => 'Hot wallet',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wallets', [
            'address' => 'TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX',
            'client_id' => $client->id,
        ]);
    }

    #[Test]
    public function deposits_cannot_be_created_or_edited_from_the_panel(): void
    {
        $this->assertFalse(DepositResource::canCreate());
        $this->assertFalse(SyncRunResource::canCreate());
    }
}
