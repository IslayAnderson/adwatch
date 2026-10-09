<?php

namespace App\Support;

class Money
{
    public const MICROS_PER_USD = 1_000_000;

    public static function usd(int $micros, int $decimals = 2): string
    {
        return '$'.number_format($micros / self::MICROS_PER_USD, $decimals);
    }

    /** Whole cents only, always rounded down — what a user can actually withdraw. */
    public static function usdFloor(int $micros): string
    {
        $cents = intdiv(max($micros, 0), self::MICROS_PER_USD / 100);

        return sprintf('$%s.%02d', number_format(intdiv($cents, 100)), $cents % 100);
    }

    /** Sub-cent display for per-impression values, e.g. $0.00932. */
    public static function precise(int $micros): string
    {
        return self::usd($micros, 5);
    }

    public static function fromUsd(float $usd): int
    {
        return (int) round($usd * self::MICROS_PER_USD);
    }
}
