<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Decimal
{
    public const QUANTITY_SCALE = 6;

    public static function normalize(int|string $value, int $scale = self::QUANTITY_SCALE): string
    {
        try {
            return (string) BigDecimal::of((string) $value)
                ->toScale($scale, RoundingMode::Unnecessary);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Invalid decimal value or precision.', 0, $exception);
        }
    }

    public static function add(int|string $left, int|string $right, int $scale = self::QUANTITY_SCALE): string
    {
        try {
            return (string) BigDecimal::of((string) $left)
                ->plus((string) $right)
                ->toScale($scale, RoundingMode::Unnecessary);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Decimal addition exceeds the supported precision.', 0, $exception);
        }
    }

    public static function multiply(int|string $left, int|string $right, int $scale = self::QUANTITY_SCALE): string
    {
        try {
            return (string) BigDecimal::of((string) $left)
                ->multipliedBy((string) $right)
                ->toScale($scale, RoundingMode::Unnecessary);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Decimal multiplication exceeds the supported precision.', 0, $exception);
        }
    }

    public static function compare(int|string $left, int|string $right): int
    {
        try {
            return BigDecimal::of((string) $left)->compareTo((string) $right);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Invalid decimal comparison.', 0, $exception);
        }
    }

    public static function isPositive(int|string $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    public static function isNegative(int|string $value): bool
    {
        return self::compare($value, '0') < 0;
    }

    public static function display(int|string $value, int $scale = self::QUANTITY_SCALE): string
    {
        $normalized = self::normalize($value, $scale);
        $negative = str_starts_with($normalized, '-');
        $unsigned = $negative ? substr($normalized, 1) : $normalized;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?: $whole;
        $fraction = rtrim($fraction, '0');

        return ($negative ? '-' : '').$whole.($fraction !== '' ? '.'.$fraction : '');
    }
}
