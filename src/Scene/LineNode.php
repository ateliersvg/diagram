<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Scene\Style\ShapeStyle;

/**
 * Straight line segment from (x1, y1) to (x2, y2).
 */
final readonly class LineNode implements NodeInterface
{
    public function __construct(
        public float $x1,
        public float $y1,
        public float $x2,
        public float $y2,
        public ShapeStyle $style,
    ) {
    }
}
