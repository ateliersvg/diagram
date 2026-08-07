<?php

declare(strict_types=1);

namespace Atelier\Diagram\Support;

/**
 * Version-independent decimal formatting for rendered coordinates.
 *
 * `round()` is not a stable formatter across the PHP versions this package
 * supports. Up to PHP 8.3 it pre-rounded its argument to ~15 significant
 * digits before rounding, so a double one ulp below a midpoint was lifted onto
 * the midpoint and then rounded up. PHP 8.4 removed that pre-rounding and
 * rounds the actual double. `round(97.57499999999998, 2)` therefore yields
 * 97.58 on PHP 8.3 and 97.57 on PHP 8.4+.
 *
 * Layout coordinates land within one ulp of a midpoint often enough that the
 * same diagram rendered on two supported runtimes produced different SVG.
 * `sprintf()` has no such pre-rounding step and agrees on every supported
 * version, so it is the formatter of record here.
 *
 * One consequence: `sprintf()` resolves exact ties to the nearest even digit
 * (the IEEE 754 default) where `round()` resolved them away from zero, so
 * 0.125 formats as 0.12 rather than 0.13. Determinism across runtimes is worth
 * more than that half-cent, and the tie behaviour is pinned by DecimalTest.
 *
 * @internal
 */
final class Decimal
{
    private const int SCALE = 2;

    private function __construct()
    {
    }

    /**
     * Rounds a coordinate to 2 decimals, identically on every supported PHP.
     */
    public static function round(float $value): float
    {
        return (float) \sprintf('%.'.self::SCALE.'F', $value);
    }

    /**
     * Formats a number rounded to 2 decimals, without trailing zeros.
     */
    public static function format(float $value): string
    {
        return (string) self::round($value);
    }
}
