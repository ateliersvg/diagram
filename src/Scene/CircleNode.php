<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\Style\ShapeStyle;

/**
 * Circle centered on (cx, cy).
 */
final readonly class CircleNode implements NodeInterface
{
    public function __construct(
        public float $cx,
        public float $cy,
        public float $r,
        public ShapeStyle $style,
    ) {
        if ($r < 0.0) {
            throw new InvalidArgumentException(\sprintf('CircleNode radius must not be negative, got %s.', $r));
        }
    }
}
