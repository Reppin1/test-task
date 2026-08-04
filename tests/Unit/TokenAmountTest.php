<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\TronScan\Support\TokenAmount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TokenAmountTest extends TestCase
{
    #[Test]
    #[DataProvider('conversions')]
    public function it_shifts_the_decimal_point_without_floats(string $baseUnits, int $decimals, string $expected): void
    {
        $this->assertSame($expected, TokenAmount::fromBaseUnits($baseUnits, $decimals));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function conversions(): array
    {
        return [
            'usdt 12.5' => ['12500000', 6, '12.500000'],
            'usdt 1 sun' => ['1', 6, '0.000001'],
            'usdt zero' => ['0', 6, '0.000000'],
            'usdt big' => ['123456789012345678', 6, '123456789012.345678'],
            'no decimals' => ['42', 0, '42'],
            'leading zeros' => ['00012500000', 6, '12.500000'],
            'negative' => ['-2500000', 6, '-2.500000'],
        ];
    }

    #[Test]
    public function it_keeps_precision_that_float_would_lose(): void
    {
        // 9007199254740993 не представимо в double.
        $this->assertSame('9007199254.740993', TokenAmount::fromBaseUnits('9007199254740993', 6));
    }

    #[Test]
    public function it_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TokenAmount::fromBaseUnits('12.5abc', 6);
    }

    #[Test]
    public function it_detects_positive_amounts(): void
    {
        $this->assertTrue(TokenAmount::isPositive('0.000001'));
        $this->assertTrue(TokenAmount::isPositive('12.500000'));

        $this->assertFalse(TokenAmount::isPositive('0.000000'));
        $this->assertFalse(TokenAmount::isPositive('-1.000000'));
        $this->assertFalse(TokenAmount::isPositive(''));
    }
}
