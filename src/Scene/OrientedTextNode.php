<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\Style\TextStyle;

/**
 * Single line of text rotated around its anchor point on the baseline.
 */
final readonly class OrientedTextNode implements NodeInterface
{
    public function __construct(
        public float $x,
        public float $y,
        public string $text,
        public TextStyle $style,
        public float $rotationDegrees = 0.0,
        public ?string $outlineColor = null,
        public float $outlineWidth = 0.0,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('OrientedTextNode text must not be empty.');
        }
        if ($outlineWidth < 0.0) {
            throw new InvalidArgumentException('OrientedTextNode outlineWidth must not be negative.');
        }
    }
}
