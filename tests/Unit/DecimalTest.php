<?php

namespace Tests\Unit;

use App\Support\Decimal;
use InvalidArgumentException;
use Tests\TestCase;

class DecimalTest extends TestCase
{
    public function test_decimal_math_never_requires_float_conversion(): void
    {
        $this->assertSame('0.300000', Decimal::add('0.1', '0.2'));
        $this->assertSame('60.000000', Decimal::multiply('2.5', '24'));
        $this->assertSame('1,234.5', Decimal::display('1234.500000'));
    }

    public function test_quantity_precision_is_enforced(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Decimal::normalize('0.0000001');
    }
}
