<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Общий помощник для тестов: фикстура TronScan + Http::fake.
 * Реальных сетевых вызовов в тестах нет (Http::preventStrayRequests).
 */
trait InteractsWithTronScan
{
    /** Адрес, на который «приходят» переводы в фикстуре. */
    protected string $fixtureWalletAddress = 'TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX';

    protected function fixturePath(): string
    {
        return database_path('fixtures/tronscan/token_trc20_transfers.json');
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixturePayload(): array
    {
        return json_decode((string) file_get_contents($this->fixturePath()), true);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function fakeTronScan(?array $payload = null): void
    {
        Http::preventStrayRequests();

        Http::fake([
            '*/api/token_trc20/transfers*' => Http::response($payload ?? $this->fixturePayload()),
        ]);
    }

    protected function fakeTronScanStatus(int $status, string $body = ''): void
    {
        Http::preventStrayRequests();

        Http::fake([
            '*/api/token_trc20/transfers*' => Http::response($body, $status),
        ]);
    }

    /**
     * Пустая страница — «новых трансферов нет».
     *
     * @return array<string, mixed>
     */
    protected function emptyPayload(): array
    {
        return ['total' => 0, 'rangeTotal' => 0, 'token_transfers' => []];
    }
}
