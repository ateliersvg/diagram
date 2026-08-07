<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * A repeating decoration painted behind the diagram, on top of the solid
 * background color. Renderer-agnostic: renderers translate it into concrete
 * output (the SVG renderer emits an SVG `<pattern>`).
 *
 * A grid tile draws one horizontal and one vertical line per cell; a dots
 * tile draws a single dot per cell. An optional heavier "major" layer repeats
 * at a larger interval, for blueprint-style minor/major grids.
 */
final readonly class BackgroundPattern
{
    /**
     * @param PatternKind $kind           grid or dots
     * @param string      $color          line/dot color (CSS color string)
     * @param float       $size           minor cell size, px
     * @param float       $lineWidth      grid line width, or dot radius for dots, px
     * @param float|null  $opacity        minor layer opacity in [0, 1], null for full
     * @param string|null $majorColor     optional heavier layer color
     * @param float       $majorSize      major cell size, px (0 disables the major layer)
     * @param float       $majorLineWidth major line width, or dot radius, px
     * @param float|null  $majorOpacity   major layer opacity in [0, 1], null for full
     */
    public function __construct(
        public PatternKind $kind,
        public string $color,
        public float $size = 16.0,
        public float $lineWidth = 0.5,
        public ?float $opacity = null,
        public ?string $majorColor = null,
        public float $majorSize = 0.0,
        public float $majorLineWidth = 0.8,
        public ?float $majorOpacity = null,
    ) {
        if ($size <= 0.0) {
            throw new InvalidArgumentException(\sprintf('BackgroundPattern size must be positive, got %s.', $size));
        }
        if ($lineWidth <= 0.0) {
            throw new InvalidArgumentException(\sprintf('BackgroundPattern lineWidth must be positive, got %s.', $lineWidth));
        }
        if (null !== $opacity && ($opacity < 0.0 || $opacity > 1.0)) {
            throw new InvalidArgumentException(\sprintf('BackgroundPattern opacity must be within [0, 1], got %s.', $opacity));
        }
        if ($majorSize < 0.0) {
            throw new InvalidArgumentException(\sprintf('BackgroundPattern majorSize must not be negative, got %s.', $majorSize));
        }
        if ($majorLineWidth <= 0.0) {
            throw new InvalidArgumentException(\sprintf('BackgroundPattern majorLineWidth must be positive, got %s.', $majorLineWidth));
        }
        if (null !== $majorOpacity && ($majorOpacity < 0.0 || $majorOpacity > 1.0)) {
            throw new InvalidArgumentException(\sprintf('BackgroundPattern majorOpacity must be within [0, 1], got %s.', $majorOpacity));
        }
    }

    /**
     * Whether a heavier major layer should be painted.
     */
    public function hasMajor(): bool
    {
        return null !== $this->majorColor && $this->majorSize > 0.0;
    }
}
