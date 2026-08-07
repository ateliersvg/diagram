<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Scene\Style\TextStyle;

/**
 * Single line of text. (x, y) is the anchor point on the baseline;
 * horizontal alignment comes from the style's anchor.
 */
final readonly class TextNode implements NodeInterface
{
    public function __construct(
        public float $x,
        public float $y,
        public string $text,
        public TextStyle $style,
    ) {
    }
}
