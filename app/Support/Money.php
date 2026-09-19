<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function format(int|string $amount, bool $withCode = false): string
    {
        $value = trim((string) $amount);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Money amount must be a plain decimal value.');
        }

        $precision = (int) config('pos.currency.precision', 2);
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;

        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        if (strlen($fraction) > $precision) {
            throw new InvalidArgumentException("Money amount exceeds the configured {$precision}-decimal precision.");
        }

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $groupedWhole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        $formatted = ($negative ? '-' : '').$groupedWhole;

        if ($precision > 0) {
            $formatted .= '.'.str_pad($fraction, $precision, '0');
        }

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
