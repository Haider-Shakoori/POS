<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function validAmounts(): array
    {
        return [
            ['0', '؋ 0.00'],
            ['1250', '؋ 1,250.00'],
            ['1250.5', '؋ 1,250.50'],
            ['001250.50', '؋ 1,250.50'],
            ['-25.75', '؋ -25.75'],
        ];
    }

    #[DataProvider('validAmounts')]
    public function test_it_formats_afn_without_floating_point_conversion(string $amount, string $expected): void
    {
        $this->assertSame($expected, Money::format($amount));
    }

    public function test_it_can_append_currency_code(): void
    {
        $this->assertSame('؋ 1,000.00 AFN', Money::format('1000', true));
    }

    public function test_it_rejects_more_precision_than_configured(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::format('10.001');
    }
}
