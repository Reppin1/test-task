<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Support;

use InvalidArgumentException;

/**
 * Перевод «сырых» единиц токена (sun / base units) в десятичную строку.
 *
 * Работает только со строками — float для денег не используется нигде.
 * ext-bcmath не требуется: сдвиг точки делается посимвольно.
 */
final class TokenAmount
{
    /**
     * @param  string  $baseUnits  например «1500000»
     * @param  int  $decimals  для USDT TRC-20 — 6
     * @return string например «1.500000»
     */
    public static function fromBaseUnits(string $baseUnits, int $decimals): string
    {
        $value = trim($baseUnits);

        if ($decimals < 0) {
            throw new InvalidArgumentException('Decimals must be >= 0.');
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');

        if ($digits === '' || ! ctype_digit($digits)) {
            throw new InvalidArgumentException(sprintf('Invalid base units value: "%s".', $baseUnits));
        }

        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return self::withScale('0', $decimals);
        }

        if ($decimals === 0) {
            return ($negative ? '-' : '').$digits;
        }

        $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);

        $integer = substr($digits, 0, -$decimals);
        $fraction = substr($digits, -$decimals);

        return ($negative ? '-' : '').$integer.'.'.$fraction;
    }

    /**
     * Строгое сравнение «> 0» без приведения к float.
     */
    public static function isPositive(string $amount): bool
    {
        $value = trim($amount);

        if ($value === '' || str_starts_with($value, '-')) {
            return false;
        }

        return (bool) preg_match('/[1-9]/', $value);
    }

    private static function withScale(string $integer, int $decimals): string
    {
        return $decimals === 0 ? $integer : $integer.'.'.str_repeat('0', $decimals);
    }
}
