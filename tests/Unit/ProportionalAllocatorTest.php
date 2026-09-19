<?php

namespace Tests\Unit;

use App\Services\Purchasing\ProportionalAllocator;
use Tests\TestCase;

class ProportionalAllocatorTest extends TestCase
{
    public function test_it_allocates_minor_units_exactly_and_deterministically(): void
    {
        $allocator = app(ProportionalAllocator::class);

        $allocations = $allocator->allocate('10.00', ['1.00', '1.00', '1.00']);

        $this->assertSame(['3.34', '3.33', '3.33'], $allocations);
        $this->assertSame('10.00', \App\Support\Decimal::add(
            \App\Support\Decimal::add($allocations[0], $allocations[1], 2),
            $allocations[2],
            2,
        ));
    }

    public function test_zero_weights_fall_back_to_equal_allocation(): void
    {
        $allocator = app(ProportionalAllocator::class);

        $this->assertSame(
            ['0.01', '0.01', '0.00'],
            $allocator->allocate('0.02', ['0', '0', '0']),
        );
    }
}
