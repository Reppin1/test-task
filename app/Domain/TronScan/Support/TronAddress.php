<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Support;

/**
 * Валидация TRON base58check адреса (mainnet, префикс 0x41 → «T...»).
 *
 * Реализация не требует ext-gmp/ext-bcmath: base58 декодируется
 * побайтовой длинной арифметикой.
 */
final class TronAddress
{
    public const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** Префикс mainnet-адреса в decoded-виде. */
    private const MAINNET_PREFIX = 0x41;

    private const DECODED_LENGTH = 25;

    /**
     * Быстрая проверка формата: «T» + 33 base58-символа.
     */
    public static function matchesFormat(string $address): bool
    {
        return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }

    /**
     * Полная проверка: формат + префикс 0x41 + sha256-checksum.
     */
    public static function isValid(string $address, ?bool $strict = null): bool
    {
        if (! self::matchesFormat($address)) {
            return false;
        }

        $strict ??= (bool) config('tronscan.strict_address_checksum', true);

        if (! $strict) {
            return true;
        }

        $decoded = self::base58Decode($address);

        if ($decoded === null || strlen($decoded) !== self::DECODED_LENGTH) {
            return false;
        }

        if (ord($decoded[0]) !== self::MAINNET_PREFIX) {
            return false;
        }

        $payload = substr($decoded, 0, 21);
        $checksum = substr($decoded, -4);

        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return hash_equals($expected, $checksum);
    }

    /**
     * Base58 → бинарная строка. null, если встретился символ вне алфавита.
     */
    public static function base58Decode(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        /** @var list<int> $bytes */
        $bytes = [];

        foreach (str_split($value) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                return null;
            }

            $carry = $index;

            for ($i = count($bytes) - 1; $i >= 0; $i--) {
                $carry += 58 * $bytes[$i];
                $bytes[$i] = $carry & 0xFF;
                $carry >>= 8;
            }

            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xFF);
                $carry >>= 8;
            }
        }

        // Ведущие «1» в base58 = ведущие нулевые байты.
        foreach (str_split($value) as $char) {
            if ($char !== '1') {
                break;
            }

            array_unshift($bytes, 0);
        }

        return implode('', array_map('chr', $bytes));
    }

    /**
     * Hex-адрес (41...) → base58check. Пригодится, если endpoint отдаёт hex.
     */
    public static function fromHex(string $hex): ?string
    {
        $hex = strtolower(ltrim($hex, '0x'));

        if (! preg_match('/^41[0-9a-f]{40}$/', $hex)) {
            return null;
        }

        $payload = hex2bin($hex);

        if ($payload === false) {
            return null;
        }

        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return self::base58Encode($payload.$checksum);
    }

    public static function base58Encode(string $binary): string
    {
        /** @var list<int> $digits */
        $digits = [];

        foreach (str_split($binary) as $char) {
            $carry = ord($char);

            for ($i = count($digits) - 1; $i >= 0; $i--) {
                $carry += $digits[$i] << 8;
                $digits[$i] = $carry % 58;
                $carry = intdiv($carry, 58);
            }

            while ($carry > 0) {
                array_unshift($digits, $carry % 58);
                $carry = intdiv($carry, 58);
            }
        }

        $encoded = '';

        foreach (str_split($binary) as $char) {
            if ($char !== "\x00") {
                break;
            }

            $encoded .= '1';
        }

        foreach ($digits as $digit) {
            $encoded .= self::ALPHABET[$digit];
        }

        return $encoded;
    }
}
