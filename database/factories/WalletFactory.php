<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\TronScan\Support\TronAddress;
use App\Models\Client;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'address' => self::randomTronAddress(),
            'label' => fake()->randomElement(['Hot wallet', 'Cold wallet', 'Deposit desk', null]),
            'is_active' => true,
            'last_synced_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Генерирует синтетический, но полностью валидный по checksum адрес
     * (0x41 + 20 случайных байт + 4 байта sha256d) — чтобы фикстуры и тесты
     * проходили строгую валидацию TronAddressRule.
     */
    public static function randomTronAddress(): string
    {
        $payload = "\x41".random_bytes(20);
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return TronAddress::base58Encode($payload.$checksum);
    }
}
