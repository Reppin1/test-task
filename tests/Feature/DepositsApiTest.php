<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepositsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_filters_deposits_by_wallet_address(): void
    {
        $wallet = Wallet::factory()->for(Client::factory())->create();
        $other = Wallet::factory()->for(Client::factory())->create();

        Deposit::factory()->count(2)->forWallet($wallet)->create();
        Deposit::factory()->forWallet($other)->create();

        $response = $this->getJson('/api/deposits?wallet='.$wallet->address);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['tx_hash', 'amount', 'status', 'explorer_url', 'wallet' => ['address'], 'client' => ['uuid']]],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_filters_by_status_and_client_uuid(): void
    {
        $client = Client::factory()->create();
        $wallet = Wallet::factory()->for($client)->create();

        Deposit::factory()->forWallet($wallet)->create();
        Deposit::factory()->forWallet($wallet)->pending()->create();

        $this->getJson('/api/deposits?client='.$client->uuid)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/deposits?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', DepositStatus::Pending->value);
    }

    #[Test]
    public function amounts_are_serialized_as_strings(): void
    {
        $wallet = Wallet::factory()->for(Client::factory())->create();

        Deposit::factory()->forWallet($wallet)->create(['amount' => '12.500000']);

        $this->getJson('/api/deposits')
            ->assertOk()
            ->assertJsonPath('data.0.amount', '12.500000');
    }

    #[Test]
    public function it_returns_a_single_deposit_by_tx_hash(): void
    {
        $wallet = Wallet::factory()->for(Client::factory())->create();
        $deposit = Deposit::factory()->forWallet($wallet)->create();

        $this->getJson('/api/deposits/'.$deposit->tx_hash)
            ->assertOk()
            ->assertJsonPath('data.tx_hash', $deposit->tx_hash);

        $this->getJson('/api/deposits/unknown-hash')->assertNotFound();
    }

    #[Test]
    public function it_validates_query_parameters(): void
    {
        $this->getJson('/api/deposits?status=whatever')->assertUnprocessable();
        $this->getJson('/api/deposits?per_page=1000')->assertUnprocessable();
    }
}
