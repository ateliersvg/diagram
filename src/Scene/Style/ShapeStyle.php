<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene\Style;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\LineStyle;

/**
 * Paint style of a shape node.
 *
 * Colors are plain CSS color strings. Dash patterns are derived from
 * lineStyle by renderers (Dashed -> "6 4", Dotted -> "2 3", scaled by
 * strokeWidth). Stroke caps and joins stay renderer-agnostic style intent.
 */
final readonly class ShapeStyle
{
    /**
     * @param string|null         $fill           fill color, null for none
     * @param string|null         $stroke         stroke color, null for none
     * @param float               $strokeWidth    stroke width, px
     * @param LineStyle           $lineStyle      stroke pattern
     * @param float|null          $opacity        0.0 to 1.0, null for opaque
     * @param StrokeLineCap|null  $strokeLineCap  stroke endpoint style, null for renderer default
     * @param StrokeLineJoin|null $strokeLineJoin stroke corner style, null for renderer default
     */
    public function __construct(
        public ?string $fill = null,
        public ?string $stroke = null,
        public float $strokeWidth = 1.0,
        public LineStyle $lineStyle = LineStyle::Solid,
        public ?float $opacity = null,
        public ?StrokeLineCap $strokeLineCap = null,
        public ?StrokeLineJoin $strokeLineJoin = null,
    ) {
        if ($strokeWidth < 0.0) {
            throw new InvalidArgumentException(\sprintf('ShapeStyle strokeWidth must not be negative, got %s.', $strokeWidth));
        }
        if (null !== $opacity && ($opacity < 0.0 || $opacity > 1.0)) {
            throw new InvalidArgumentException(\sprintf('ShapeStyle opacity must be between 0 and 1, got %s.', $opacity));
        }
    }

    /**
     * Creates a fill-only style.
     */
    public static function filled(string $color): self
    {
        return new self(fill: $color);
    }

    /**
     * Creates a stroke-only style.
     */
    public static function stroked(string $color, float $width = 1.0): self
    {
        return new self(
            stroke: $color,
            strokeWidth: $width,
            strokeLineCap: StrokeLineCap::Round,
            strokeLineJoin: StrokeLineJoin::Round,
        );
    }
}
