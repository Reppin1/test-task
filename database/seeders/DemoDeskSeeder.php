<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

/**
 * Демо-данные: 2 клиента (один заблокирован) и 3 кошелька.
 *
 * Адреса — публичные TRON-адреса с реальной историей USDT: с настоящим
 * TRONSCAN_API_KEY и TRONSCAN_DRIVER=http по ним придут живые депозиты.
 * В режиме TRONSCAN_DRIVER=fake адрес роли не играет — данные из фикстуры.
 */
class DemoDeskSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Client::query()->updateOrCreate(
            ['email' => 'ops@acme-trading.example'],
            [
                'name' => 'Acme Trading',
                'status' => ClientStatus::Active,
                'notes' => 'Основной клиент демо-стенда.',
            ],
        );

        $blocked = Client::query()->updateOrCreate(
            ['email' => 'risk@northwind.example'],
            [
                'name' => 'Northwind Capital',
                'status' => ClientStatus::Blocked,
                'notes' => 'Заблокирован комплаенсом: депозиты всё равно фиксируются, но помечаются в админке.',
            ],
        );

        $wallets = [
            [
                'client_id' => $acme->id,
                'address' => 'TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX',
                'label' => 'Acme · hot wallet',
                'is_active' => true,
            ],
            [
                'client_id' => $acme->id,
                'address' => 'TMuA6YqfCeX8EhbfYEg5y7S4DqzSJireY9',
                'label' => 'Acme · архивный (неактивен)',
                'is_active' => false,
            ],
            [
                'client_id' => $blocked->id,
                'address' => 'TJDENsfBJs4RFETt1X1W8wMDc8M5XnJhCe',
                'label' => 'Northwind · deposit',
                'is_active' => true,
            ],
        ];

        foreach ($wallets as $wallet) {
            Wallet::query()->updateOrCreate(
                ['address' => $wallet['address']],
                $wallet,
            );
        }

        $this->command->info('Демо-данные: 2 клиента, 3 кошелька. Запустите `php artisan deposits:sync`.');
    }
}
