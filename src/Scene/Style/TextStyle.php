<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene\Style;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Layout\Text\FontWeight;

/**
 * Paint style of a text node.
 *
 * Colors are plain CSS color strings.
 */
final readonly class TextStyle
{
    /**
     * @param string     $fontFamily font family
     * @param float      $fontSize   font size, px
     * @param FontWeight $fontWeight font weight
     * @param TextAnchor $anchor     horizontal alignment relative to the anchor point
     * @param string     $fill       text color
     */
    public function __construct(
        public string $fontFamily,
        public float $fontSize,
        public FontWeight $fontWeight = FontWeight::Normal,
        public TextAnchor $anchor = TextAnchor::Start,
        public string $fill = '#000000',
    ) {
        if ($fontSize <= 0.0) {
            throw new InvalidArgumentException(\sprintf('TextStyle fontSize must be positive, got %s.', $fontSize));
        }
    }
}
