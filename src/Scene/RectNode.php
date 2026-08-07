<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\Style\ShapeStyle;

/**
 * Axis-aligned rectangle, optionally with rounded corners.
 */
final readonly class RectNode implements NodeInterface
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ShapeStyle $style,
        public float $cornerRadius = 0.0,
    ) {
        if ($width < 0.0 || $height < 0.0) {
            throw new InvalidArgumentException(\sprintf('RectNode dimensions must not be negative, got %s x %s.', $width, $height));
        }
        if ($cornerRadius < 0.0) {
            throw new InvalidArgumentException(\sprintf('RectNode cornerRadius must not be negative, got %s.', $cornerRadius));
        }
    }
}
