<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function format(int|float|string $amount, bool $withCode = false): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        $precision = (int) config('pos.currency.precision', 2);
        $formatted = number_format((float) $amount, $precision, '.', ',');
        $symbol = (string) config('pos.currency.symbol', '؋');

        return $withCode
            ? sprintf('%s %s AFN', $symbol, $formatted)
            : sprintf('%s %s', $symbol, $formatted);
    }

    public static function code(): string
    {
        return (string) config('pos.currency.code', 'AFN');
    }
}
