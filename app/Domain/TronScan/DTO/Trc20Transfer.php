<?php

declare(strict_types=1);

namespace App\Domain\TronScan\DTO;

use App\Domain\TronScan\Support\TokenAmount;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Один TRC-20 трансфер из ответа TronScan.
 *
 * Формат источника (GET /api/token_trc20/transfers):
 *   transaction_id, from_address, to_address, quant (base units),
 *   block_ts (ms), contract_address, contract_ret / finalResult,
 *   confirmed, tokenInfo { tokenAbbr, tokenDecimal }
 */
final readonly class Trc20Transfer
{
    /**
     * @param  string  $amount  десятичная строка, например «12.500000»
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $txHash,
        public string $fromAddress,
        public string $toAddress,
        public string $amount,
        public string $rawAmount,
        public int $decimals,
        public string $tokenSymbol,
        public ?string $contractAddress,
        public ?Carbon $blockTimestamp,
        public bool $confirmed,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload, int $defaultDecimals = 6): self
    {
        $decimals = (int) (Arr::get($payload, 'tokenInfo.tokenDecimal') ?? $defaultDecimals);
        $rawAmount = (string) (Arr::get($payload, 'quant') ?? Arr::get($payload, 'amount_str') ?? '0');

        $blockTs = Arr::get($payload, 'block_ts') ?? Arr::get($payload, 'block_timestamp');

        return new self(
            txHash: (string) (Arr::get($payload, 'transaction_id') ?? Arr::get($payload, 'hash') ?? ''),
            fromAddress: (string) (Arr::get($payload, 'from_address') ?? ''),
            toAddress: (string) (Arr::get($payload, 'to_address') ?? ''),
            amount: TokenAmount::fromBaseUnits($rawAmount, $decimals),
            rawAmount: $rawAmount,
            decimals: $decimals,
            tokenSymbol: (string) (Arr::get($payload, 'tokenInfo.tokenAbbr') ?? 'USDT'),
            contractAddress: Arr::get($payload, 'contract_address') ?? Arr::get($payload, 'tokenInfo.tokenId'),
            blockTimestamp: self::parseTimestamp($blockTs),
            confirmed: self::isConfirmed($payload),
            raw: self::trimRaw($payload),
        );
    }

    public function isPositive(): bool
    {
        return TokenAmount::isPositive($this->amount);
    }

    public function isIncomingTo(string $address): bool
    {
        return $this->toAddress === $address;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isConfirmed(array $payload): bool
    {
        $result = Arr::get($payload, 'contract_ret') ?? Arr::get($payload, 'finalResult');
        $confirmedFlag = Arr::get($payload, 'confirmed');

        $resultOk = $result === null || strtoupper((string) $result) === 'SUCCESS';
        $flagOk = $confirmedFlag === null || (bool) $confirmedFlag === true;

        return $resultOk && $flagOk;
    }

    private static function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }

        if (is_numeric($value)) {
            $number = (int) $value;

            // TronScan отдаёт миллисекунды.
            return $number > 9_999_999_999
                ? Carbon::createFromTimestampMs($number)
                : Carbon::createFromTimestamp($number);
        }

        return Carbon::parse((string) $value);
    }

    /**
     * Урезаем payload до полезных полей — в БД не нужен весь ответ.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function trimRaw(array $payload): array
    {
        return Arr::only($payload, [
            'transaction_id',
            'from_address',
            'to_address',
            'quant',
            'block_ts',
            'block',
            'contract_address',
            'contract_ret',
            'finalResult',
            'confirmed',
            'tokenInfo',
        ]);
    }
}
