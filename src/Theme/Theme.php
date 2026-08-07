<?php

declare(strict_types=1);

namespace Atelier\Diagram\Theme;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\BackgroundPattern;
use Atelier\Diagram\Scene\PatternKind;

/**
 * Visual theme consumed by layout engines.
 *
 * By the time a Scene exists, all styling is resolved into node styles --
 * renderers never read the theme.
 */
final readonly class Theme
{
    /**
     * @param string                 $backgroundColor   diagram background color
     * @param string                 $nodeFillColor     default node fill
     * @param string                 $nodeStrokeColor   default node outline
     * @param string                 $textColor         primary text color
     * @param string                 $mutedTextColor    secondary text color (legends, captions)
     * @param list<string>           $accentColors      non-empty list, cycled for git lanes / venn circles
     * @param string                 $fontFamily        font family for all text
     * @param float                  $fontSize          base font size, px
     * @param float                  $spacingUnit       base spacing unit, px, all layout gaps are multiples of it
     * @param float                  $strokeWidth       base stroke width, px, node outlines and edges derive from it
     * @param float|null             $minNodeWidth      optional minimum node width, px
     * @param float|null             $minNodeHeight     optional minimum node height, px
     * @param BackgroundPattern|null $backgroundPattern optional canvas decoration transferred onto the Scene
     */
    public function __construct(
        public string $backgroundColor,
        public string $nodeFillColor,
        public string $nodeStrokeColor,
        public string $textColor,
        public string $mutedTextColor,
        public array $accentColors,
        public string $fontFamily,
        public float $fontSize,
        public float $spacingUnit,
        public float $strokeWidth = 1.5,
        public ?float $minNodeWidth = null,
        public ?float $minNodeHeight = null,
        public ?BackgroundPattern $backgroundPattern = null,
    ) {
        if ([] === $accentColors) {
            throw new InvalidArgumentException('Theme accentColors must be a non-empty list of colors.');
        }
        if ($fontSize <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Theme fontSize must be positive, got %s.', $fontSize));
        }
        if ($spacingUnit <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Theme spacingUnit must be positive, got %s.', $spacingUnit));
        }
        if ($strokeWidth <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Theme strokeWidth must be positive, got %s.', $strokeWidth));
        }
        if (null !== $minNodeWidth && $minNodeWidth <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Theme minNodeWidth must be positive when set, got %s.', $minNodeWidth));
        }
        if (null !== $minNodeHeight && $minNodeHeight <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Theme minNodeHeight must be positive when set, got %s.', $minNodeHeight));
        }
    }

    /**
     * The standard look: light background, dark text, 6 accent colors.
     */
    public static function default(): self
    {
        return new self(
            backgroundColor: '#ffffff',
            nodeFillColor: '#f1f5f9',
            nodeStrokeColor: '#334155',
            textColor: '#1e293b',
            mutedTextColor: '#64748b',
            accentColors: ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#9333ea', '#0d9488'],
            fontFamily: 'Helvetica, Arial, sans-serif',
            fontSize: 14.0,
            spacingUnit: 8.0,
            strokeWidth: 1.5,
        );
    }

    /**
     * A dark look for slate/navy surfaces: deep background, light text,
     * cyan node outlines, 6 saturated accent colors.
     */
    public static function dark(): self
    {
        return new self(
            backgroundColor: '#020617',
            nodeFillColor: '#111827',
            nodeStrokeColor: '#67e8f9',
            textColor: '#f8fafc',
            mutedTextColor: '#94a3b8',
            accentColors: ['#22d3ee', '#a78bfa', '#fb7185', '#34d399', '#fbbf24', '#60a5fa'],
            fontFamily: 'Helvetica, Arial, sans-serif',
            fontSize: 15.0,
            spacingUnit: 9.0,
            strokeWidth: 1.8,
        );
    }

    /**
     * A technical-drawing look: deep navy field with a cyan minor grid and a
     * heavier major grid, mono text, thin strokes.
     */
    public static function blueprint(): self
    {
        return new self(
            backgroundColor: '#06182b',
            nodeFillColor: '#08243d',
            nodeStrokeColor: '#e6fbff',
            textColor: '#ffffff',
            mutedTextColor: '#bae6fd',
            accentColors: ['#67e8f9', '#38bdf8', '#e6fbff'],
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
            fontSize: 13.0,
            spacingUnit: 8.0,
            strokeWidth: 0.95,
            backgroundPattern: new BackgroundPattern(
                PatternKind::Grid,
                '#67e8f9',
                size: 16.0,
                lineWidth: 0.45,
                opacity: 0.26,
                majorColor: '#e6fbff',
                majorSize: 80.0,
                majorLineWidth: 0.8,
                majorOpacity: 0.22,
            ),
        );
    }

    /**
     * A grayscale ink look: white field, near-black outlines and text, no
     * accent hues.
     */
    public static function mono(): self
    {
        return new self(
            backgroundColor: '#ffffff',
            nodeFillColor: '#ffffff',
            nodeStrokeColor: '#111111',
            textColor: '#111111',
            mutedTextColor: '#4b5563',
            accentColors: ['#111111', '#374151', '#6b7280'],
            fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            fontSize: 14.0,
            spacingUnit: 8.0,
            strokeWidth: 1.6,
        );
    }

    /**
     * A warm paper look: soft beige field, low-contrast sand nodes, hairline
     * strokes.
     */
    public static function neutral(): self
    {
        return new self(
            backgroundColor: '#f5f3ef',
            nodeFillColor: '#e4ded4',
            nodeStrokeColor: '#d8d2c8',
            textColor: '#201f1c',
            mutedTextColor: '#706a60',
            accentColors: ['#b08968', '#9c6644', '#7f5539'],
            fontFamily: 'Optima, Candara, "Gill Sans", sans-serif',
            fontSize: 14.0,
            spacingUnit: 8.0,
            strokeWidth: 0.7,
        );
    }
}
