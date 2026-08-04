<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    protected $model = Deposit::class;

    public function definition(): array
    {
        $wallet = Wallet::factory();

        return [
            'wallet_id' => $wallet,
            'tx_hash' => bin2hex(random_bytes(32)),
            'from_address' => WalletFactory::randomTronAddress(),
            'to_address' => WalletFactory::randomTronAddress(),
            'amount' => number_format(fake()->randomFloat(2, 1, 5000), 6, '.', ''),
            'token_symbol' => 'USDT',
            'contract_address' => config('tronscan.usdt_contract'),
            'block_timestamp' => fake()->dateTimeBetween('-30 days'),
            'status' => DepositStatus::Confirmed,
            'raw_payload' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => DepositStatus::Pending]);
    }

    public function ignored(): static
    {
        return $this->state(fn (): array => [
            'status' => DepositStatus::Ignored,
            'amount' => '0.000000',
        ]);
    }

    public function forWallet(Wallet $wallet): static
    {
        return $this->state(fn (): array => [
            'wallet_id' => $wallet->id,
            'to_address' => $wallet->address,
        ]);
    }
}
