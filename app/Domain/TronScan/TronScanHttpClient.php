<?php

declare(strict_types=1);

namespace App\Domain\TronScan;

use App\Domain\TronScan\Contracts\TronScanClient;
use App\Domain\TronScan\DTO\TransferPage;
use App\Domain\TronScan\DTO\Trc20Transfer;
use App\Domain\TronScan\Exceptions\TronScanRateLimitException;
use App\Domain\TronScan\Exceptions\TronScanRequestException;
use App\Domain\TronScan\Exceptions\TronScanResponseException;
use App\Domain\TronScan\Exceptions\TronScanServerException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Реальный HTTP-клиент TronScan.
 *
 * GET {base_url}/api/token_trc20/transfers
 *     ?relatedAddress={address}&contract_address={usdt}&limit={n}&start={offset}
 *   Header: TRON-PRO-API-KEY: {key}
 *
 * Ретраим только сетевые ошибки, 429 и 5xx; 4xx отдаём сразу доменным исключением.
 */
final class TronScanHttpClient implements TronScanClient
{
    /**
     * @param  array<string, mixed>  $config  config('tronscan')
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $config,
    ) {}

    public function transfers(
        string $address,
        int $limit = 50,
        int $start = 0,
        ?string $contractAddress = null,
    ): TransferPage {
        $query = [
            'relatedAddress' => $address,
            'contract_address' => $contractAddress ?? (string) $this->config['usdt_contract'],
            'limit' => $limit,
            'start' => $start,
        ];

        try {
            $response = $this->request()->get((string) $this->config['transfers_endpoint'], $query);
        } catch (ConnectionException $e) {
            throw new TronScanServerException(
                'TronScan is unreachable: '.$e->getMessage(),
                previous: $e,
            );
        } catch (RequestException $e) {
            throw $this->mapStatus($e->response->status(), $e->response->body(), $e);
        }

        $this->ensureSuccessful($response);

        return $this->mapPage($response, $start, $limit);
    }

    private function request(): PendingRequest
    {
        $headers = ['Accept' => 'application/json'];

        $apiKey = $this->config['api_key'] ?? null;

        if (is_string($apiKey) && $apiKey !== '') {
            $headers['TRON-PRO-API-KEY'] = $apiKey;
        }

        return $this->http
            ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withHeaders($headers)
            ->timeout((int) $this->config['timeout'])
            ->connectTimeout((int) $this->config['connect_timeout'])
            ->retry(
                times: max(1, (int) $this->config['retry_times']),
                sleepMilliseconds: (int) $this->config['retry_sleep_ms'],
                when: fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw $this->mapStatus($response->status(), $response->body());
    }

    private function mapStatus(int $status, ?string $body, ?Throwable $previous = null): TronScanRequestException|TronScanServerException
    {
        $snippet = $body === null ? '' : ' Body: '.mb_substr($body, 0, 300);

        return match (true) {
            $status === 429 => new TronScanRateLimitException(
                'TronScan rate limit reached (HTTP 429). Set TRONSCAN_API_KEY or lower the sync frequency.'.$snippet,
                $status,
                $body,
                $previous,
            ),
            $status >= 500 => new TronScanServerException(
                sprintf('TronScan server error (HTTP %d).%s', $status, $snippet),
                $status,
                $body,
                $previous,
            ),
            default => new TronScanRequestException(
                sprintf('TronScan rejected the request (HTTP %d).%s', $status, $snippet),
                $status,
                $body,
                $previous,
            ),
        };
    }

    private function mapPage(Response $response, int $start, int $limit): TransferPage
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new TronScanResponseException('TronScan returned a non-JSON payload.');
        }

        // token_transfers — основной формат; data — запасной (альтернативные эндпоинты).
        $items = $payload['token_transfers'] ?? $payload['data'] ?? null;

        if (! is_array($items)) {
            throw new TronScanResponseException('TronScan response has no "token_transfers" array.');
        }

        $decimals = (int) $this->config['usdt_decimals'];

        $transfers = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $transfer = Trc20Transfer::fromApi($item, $decimals);

            if ($transfer->txHash === '') {
                continue;
            }

            $transfers[] = $transfer;
        }

        $total = (int) ($payload['total'] ?? $payload['rangeTotal'] ?? count($transfers));

        return new TransferPage($transfers, $total, $start, $limit);
    }
}
