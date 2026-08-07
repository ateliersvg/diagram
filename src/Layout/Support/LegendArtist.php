<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Model\Legend;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Legend\LegendBlock;
use Atelier\Layout\Legend\PlacedLegend;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Renders legends uniformly across layout engines.
 *
 * A legend is a vertical list of rows, each a color swatch followed by a
 * muted label. Engines decide where the block goes (bottom-left, below the
 * content) and what the entries are; the artist owns the row geometry so
 * every diagram type draws the same legend.
 *
 * @internal
 */
final class LegendArtist
{
    /** Legend font size, as a factor of the theme font size. */
    private const float FONT_SCALE = 0.85;

    /** Swatch side, in spacing units. */
    private const float SWATCH_UNITS = 1.5;

    /** Gap between swatch and label, in spacing units. */
    private const float SWATCH_GAP_UNITS = 1.0;

    /** Vertical gap between rows, in spacing units. */
    private const float ROW_GAP_UNITS = 0.5;

    public function __construct(
        private readonly TextMeasurerInterface $textMeasurer,
    ) {
    }

    /**
     * Measures the legend block.
     *
     * @return array{float, float} width and height; (0, 0) for an empty legend
     */
    public function measure(Legend $legend, Theme $theme): array
    {
        if ([] === $legend->entries) {
            return [0.0, 0.0];
        }

        $layout = $this->layout($legend, $theme, Rect::fromSize(1_000_000.0, 1_000_000.0));

        return [$layout->frame->width, $layout->frame->height];
    }

    /**
     * Builds the legend nodes with the block's top-left corner at (x, y).
     *
     * @return list<NodeInterface>
     */
    public function nodes(Legend $legend, Theme $theme, float $x, float $y): array
    {
        $unit = $theme->spacingUnit;
        $fontSize = self::FONT_SCALE * $theme->fontSize;
        $labelStyle = new TextStyle($theme->fontFamily, $fontSize, fill: $theme->mutedTextColor);
        $layout = $this->layout($legend, $theme, new Rect($x, $y, 1_000_000.0, 1_000_000.0));

        $nodes = [];
        foreach ($layout->entries as $index => $entryLayout) {
            $entry = $legend->entries[$index];
            $metrics = $this->textMeasurer->measureLine($entry->label, $fontSize);
            $nodes[] = new RectNode(
                $entryLayout->swatchFrame->x,
                $entryLayout->swatchFrame->y,
                $entryLayout->swatchFrame->width,
                $entryLayout->swatchFrame->height,
                ShapeStyle::filled($entry->color),
                cornerRadius: 0.25 * $unit,
            );
            $nodes[] = new TextNode(
                $entryLayout->labelFrame->x,
                $entryLayout->labelFrame->y + $metrics->ascent,
                $entry->label,
                $labelStyle,
            );
        }

        return $nodes;
    }

    private function layout(Legend $legend, Theme $theme, Rect $available): PlacedLegend
    {
        $unit = $theme->spacingUnit;
        $fontSize = self::FONT_SCALE * $theme->fontSize;
        $layout = LegendBlock::vertical('legend')
            ->gap(self::ROW_GAP_UNITS * $unit)
            ->labelGap(self::SWATCH_GAP_UNITS * $unit)
            ->swatchSize(self::SWATCH_UNITS * $unit, self::SWATCH_UNITS * $unit);

        foreach ($legend->entries as $index => $entry) {
            $metrics = $this->textMeasurer->measureLine($entry->label, $fontSize);
            $layout = $layout->add((string) $index, $metrics->width, $metrics->height);
        }

        return $layout->place($available);
    }
}
