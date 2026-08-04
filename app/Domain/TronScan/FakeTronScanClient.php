<?php

declare(strict_types=1);

namespace App\Domain\TronScan;

use App\Domain\TronScan\Contracts\TronScanClient;
use App\Domain\TronScan\DTO\TransferPage;
use App\Domain\TronScan\DTO\Trc20Transfer;
use App\Domain\TronScan\Exceptions\TronScanResponseException;

/**
 * Офлайн-реализация клиента: отдаёт трансферы из JSON-фикстуры.
 *
 * Включается через TRONSCAN_DRIVER=fake — демо и локальная разработка
 * работают без API-ключа и без сети.
 *
 * Чтобы фикстура «подходила» любому кошельку, входящие трансферы (адресованные
 * «эталонному» адресу фикстуры — to_address первого элемента) переписываются
 * на запрошенный адрес, а tx_hash делается детерминированным для пары
 * (адрес, исходный хеш): иначе два кошелька подрались бы за unique-индекс
 * deposits.tx_hash.
 *
 * Остальные трансферы (исходящие, «чужие») остаются как есть — их отфильтрует
 * DepositSyncService, что заодно демонстрирует правило «только входящие».
 */
final class FakeTronScanClient implements TronScanClient
{
    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    /**
     * @param  array<string, mixed>  $config  config('tronscan')
     */
    public function __construct(
        private readonly array $config,
        private readonly ?string $fixturePath = null,
    ) {}

    public function transfers(
        string $address,
        int $limit = 50,
        int $start = 0,
        ?string $contractAddress = null,
    ): TransferPage {
        $items = $this->items();
        $total = count($items);
        $placeholder = (string) ($items[0]['to_address'] ?? '');

        $decimals = (int) $this->config['usdt_decimals'];

        $transfers = [];

        foreach (array_slice($items, $start, $limit) as $item) {
            $transfers[] = Trc20Transfer::fromApi(
                $this->rewriteForAddress($item, $address, $placeholder),
                $decimals,
            );
        }

        return new TransferPage($transfers, $total, $start, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(): array
    {
        $payload = $this->payload ??= $this->loadFixture();

        $items = $payload['token_transfers'] ?? null;

        if (! is_array($items)) {
            throw new TronScanResponseException('Fixture has no "token_transfers" array.');
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(): array
    {
        $path = $this->fixturePath ?? (string) $this->config['fixture_path'];

        if (! is_file($path)) {
            throw new TronScanResponseException(sprintf('TronScan fixture not found: %s', $path));
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new TronScanResponseException(sprintf('TronScan fixture is not valid JSON: %s', $path));
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function rewriteForAddress(array $item, string $address, string $placeholder): array
    {
        $to = (string) ($item['to_address'] ?? '');

        if ($to !== $placeholder || $to === $address) {
            return $item;
        }

        $item['to_address'] = $address;
        $item['transaction_id'] = hash('sha256', $address.'|'.(string) ($item['transaction_id'] ?? ''));

        return $item;
    }
}
