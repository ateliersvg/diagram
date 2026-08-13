<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Connection\ConnectionLabel;
use Atelier\Layout\Connection\ConnectionLabelPlacement;
use Atelier\Layout\Connection\OrthogonalConnection;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Insets;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\Geometry\Size;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Places connection labels uniformly across layout engines.
 *
 * Connection labels are rendered as a small background halo plus centered text.
 * Engines provide the connection, avoid index, and text style; this artist owns
 * the shared padding, baseline, and corner-radius policy.
 *
 * @internal
 */
final readonly class ConnectionLabelArtist
{
    private const float HORIZONTAL_PADDING_UNITS = 0.75;

    private const float VERTICAL_PADDING_UNITS = 0.35;

    private const float AVOID_PADDING_UNITS = 0.5;

    private const float CORNER_RADIUS_UNITS = 0.45;

    private const float MAX_WIDTH_UNITS = 12.0;

    private const float LINE_HEIGHT = 1.5;

    /** Browser system-font metrics are wider than CharWidthTextMeasurer. */
    private const float TEXT_WIDTH_SAFETY_FACTOR = 1.3;

    public function __construct(
        private TextMeasurerInterface $textMeasurer,
    ) {
    }

    /**
     * @return list<NodeInterface>
     */
    public function nodes(
        string $text,
        OrthogonalConnection $connection,
        RectIndex $avoidIndex,
        Theme $theme,
        TextStyle $textStyle,
        ConnectionLabelPlacement $placement = ConnectionLabelPlacement::Centered,
    ): array {
        $unit = $theme->spacingUnit;
        $maxTextWidth = self::MAX_WIDTH_UNITS * $unit;
        $metrics = $this->textMeasurer->wrap($text, $maxTextWidth, $textStyle->fontSize, self::LINE_HEIGHT, true, $textStyle->fontWeight);
        $safeTextWidth = min($maxTextWidth * self::TEXT_WIDTH_SAFETY_FACTOR, $metrics->width * self::TEXT_WIDTH_SAFETY_FACTOR);
        $placedLabel = ConnectionLabel::for($connection)
            ->size(new Size(
                $safeTextWidth + 2.0 * self::HORIZONTAL_PADDING_UNITS * $unit,
                $metrics->height + 2.0 * self::VERTICAL_PADDING_UNITS * $unit,
            ))
            ->padding(Insets::all(self::AVOID_PADDING_UNITS * $unit))
            ->placement($placement)
            ->avoid($avoidIndex)
            ->place();

        $textFrame = new Rect(
            $placedLabel->frame->x + self::HORIZONTAL_PADDING_UNITS * $unit,
            $placedLabel->frame->y + self::VERTICAL_PADDING_UNITS * $unit,
            $safeTextWidth,
            $metrics->height,
        );
        $layout = TextBlock::of('connection.label', $text, $textStyle->fontSize)
            ->weight($textStyle->fontWeight)
            ->lineHeight(self::LINE_HEIGHT)
            ->breakWords()
            ->align(Alignment::Center, Alignment::Center)
            ->layout(new LayoutContext(textMeasurer: $this->textMeasurer), $textFrame);

        $nodes = [
            new RectNode(
                $placedLabel->frame->x,
                $placedLabel->frame->y,
                $placedLabel->frame->width,
                $placedLabel->frame->height,
                new ShapeStyle(fill: $theme->backgroundColor, opacity: 0.96),
                cornerRadius: self::CORNER_RADIUS_UNITS * $unit,
            ),
        ];
        foreach ($layout->lines as $line) {
            $nodes[] = new TextNode(
                $line->frame->x + $line->frame->width / 2.0,
                $line->baseline,
                $line->text,
                $textStyle,
            );
        }

        return $nodes;
    }
}
