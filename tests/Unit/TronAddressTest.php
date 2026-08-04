<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\TronScan\Support\TronAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TronAddressTest extends TestCase
{
    #[Test]
    #[DataProvider('validAddresses')]
    public function it_accepts_real_tron_addresses(string $address): void
    {
        $this->assertTrue(TronAddress::matchesFormat($address));
        $this->assertTrue(TronAddress::isValid($address, strict: true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validAddresses(): array
    {
        return [
            'usdt contract' => ['TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'],
            'exchange hot wallet' => ['TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX'],
            'another wallet' => ['TJDENsfBJs4RFETt1X1W8wMDc8M5XnJhCe'],
        ];
    }

    #[Test]
    #[DataProvider('invalidAddresses')]
    public function it_rejects_malformed_addresses(string $address): void
    {
        $this->assertFalse(TronAddress::isValid($address, strict: true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAddresses(): array
    {
        return [
            'wrong prefix' => ['BAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX'],
            'too short' => ['TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBi'],
            'too long' => ['TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiXX'],
            'broken checksum' => ['TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiY'],
            'base58 alphabet violation (0)' => ['T0UN6FwrnwwmaEqYcckffC7wYmbaS6cBiX'],
            'ethereum address' => ['0x71C7656EC7ab88b098defB751B7401B5f6d8976F'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function non_strict_mode_only_checks_the_format(): void
    {
        // Checksum битый, но формат верный.
        $address = 'TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiY';

        $this->assertFalse(TronAddress::isValid($address, strict: true));
        $this->assertTrue(TronAddress::isValid($address, strict: false));
    }

    #[Test]
    public function it_converts_hex_addresses_to_base58(): void
    {
        $base58 = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
        $decoded = TronAddress::base58Decode($base58);

        $this->assertNotNull($decoded);

        $hex = bin2hex(substr($decoded, 0, 21));

        $this->assertSame($base58, TronAddress::fromHex($hex));
    }
}
