<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Contracts;

use App\Domain\TronScan\DTO\TransferPage;
use App\Domain\TronScan\Exceptions\TronScanException;

/**
 * Read-only контракт клиента TronScan.
 *
 * Реализации:
 *  - App\Domain\TronScan\TronScanHttpClient — реальный HTTP;
 *  - App\Domain\TronScan\FakeTronScanClient — фикстура (демо/офлайн).
 *
 * Биндинг: App\Providers\TronScanServiceProvider (config('tronscan.driver')).
 */
interface TronScanClient
{
    /**
     * Входящие/исходящие TRC-20 трансферы, связанные с адресом.
     *
     * @param  string  $address  TRON base58 адрес кошелька
     * @param  int  $limit  размер страницы
     * @param  int  $start  смещение
     * @param  string|null  $contractAddress  контракт токена (по умолчанию — USDT из конфига)
     *
     * @throws TronScanException
     */
    public function transfers(
        string $address,
        int $limit = 50,
        int $start = 0,
        ?string $contractAddress = null,
    ): TransferPage;
}
