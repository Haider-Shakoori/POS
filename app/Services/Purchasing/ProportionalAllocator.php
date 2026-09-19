<?php

namespace App\Services\Purchasing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class ProportionalAllocator
{
    public function allocate(string $amount, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $amountCents = BigDecimal::of($amount)
            ->multipliedBy('100')
            ->toScale(0, RoundingMode::Unnecessary);

        if ($amountCents->isNegative()) {
            throw new InvalidArgumentException('Allocation amount cannot be negative.');
        }

        $normalizedWeights = array_map(
            fn ($weight) => BigDecimal::of((string) $weight),
            array_values($weights),
        );

        foreach ($normalizedWeights as $weight) {
            if ($weight->isNegative()) {
                throw new InvalidArgumentException('Allocation weights cannot be negative.');
            }
        }

        $totalWeight = array_reduce(
            $normalizedWeights,
            fn (?BigDecimal $carry, BigDecimal $weight) => ($carry ?? BigDecimal::zero())->plus($weight),
        );

        if ($totalWeight->isZero()) {
            $normalizedWeights = array_fill(0, count($normalizedWeights), BigDecimal::one());
            $totalWeight = BigDecimal::of((string) count($normalizedWeights));
        }

        $allocatedCents = [];
        $remainders = [];
        $allocatedTotal = BigDecimal::zero();

        foreach ($normalizedWeights as $index => $weight) {
            $raw = $amountCents
                ->multipliedBy($weight)
                ->dividedBy($totalWeight, 12, RoundingMode::Down);

            $floor = $raw->toScale(0, RoundingMode::Down);
            $allocatedCents[$index] = $floor;
            $remainders[$index] = $raw->minus($floor);
            $allocatedTotal = $allocatedTotal->plus($floor);
        }

        $remaining = (int) (string) $amountCents->minus($allocatedTotal);

        if ($remaining > 0) {
            $order = array_keys($remainders);

            usort($order, function (int $left, int $right) use ($remainders): int {
                $comparison = $remainders[$right]->compareTo($remainders[$left]);

                return $comparison !== 0 ? $comparison : $left <=> $right;
            });

            for ($i = 0; $i < $remaining; $i++) {
                $index = $order[$i % count($order)];
                $allocatedCents[$index] = $allocatedCents[$index]->plus(1);
            }
        }

        ksort($allocatedCents);

        return array_map(
            fn (BigDecimal $cents) => (string) $cents->dividedBy('100', 2, RoundingMode::Unnecessary),
            $allocatedCents,
        );
    }
}
