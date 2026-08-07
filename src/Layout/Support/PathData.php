<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Support\Decimal;
use Atelier\Layout\Connection\OrthogonalConnection;

/**
 * Builds SVG path-data strings with uniformly formatted coordinates.
 *
 * Layout-internal helper shared by the layout engines so every PathNode in
 * a Scene uses the same number format (rounded to 2 decimals, no trailing
 * zeros).
 *
 * @internal
 */
final class PathData
{
    private function __construct()
    {
    }

    /**
     * Formats a path-data number: rounded to 2 decimals, no trailing zeros.
     */
    public static function number(float $value): string
    {
        return Decimal::format($value);
    }

    /**
     * A cubic Bezier segment from (sx, sy) to (tx, ty) with control points
     * (c1x, c1y) and (c2x, c2y).
     */
    public static function cubic(float $sx, float $sy, float $c1x, float $c1y, float $c2x, float $c2y, float $tx, float $ty): string
    {
        return \sprintf(
            'M %s %s C %s %s %s %s %s %s',
            self::number($sx),
            self::number($sy),
            self::number($c1x),
            self::number($c1y),
            self::number($c2x),
            self::number($c2y),
            self::number($tx),
            self::number($ty),
        );
    }

    /**
     * Converts an orthogonal layout connection to SVG path data.
     */
    public static function connection(OrthogonalConnection $connection): string
    {
        $commands = [];
        foreach ($connection->points as $index => $point) {
            $commands[] = (0 === $index ? 'M ' : 'L ').self::number($point->x).' '.self::number($point->y);
        }

        return implode(' ', $commands);
    }
}
