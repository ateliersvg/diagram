<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Timeline;

use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class TimelineLayoutEngine
{
    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
    }

    public function layout(TimelineDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $titleHeight = null === $diagram->title ? 0.0 : 7.0 * $su;
        $cardHeight = 8.4 * $su;
        $dateHeight = 3.0 * $su;
        $eventGap = 4.0 * $su;
        $laneHeight = 2.0 * $cardHeight + 7.0 * $su;

        $sectionTitleWidth = 0.0;
        $cardWidth = max(17.0 * $su, (float) $theme->minNodeWidth);
        $maxEvents = 1;
        foreach ($diagram->sections as $section) {
            $maxEvents = max($maxEvents, \count($section->events));
            $sectionTitleWidth = max(
                $sectionTitleWidth,
                $this->measurer->measureLine($section->title, 0.95 * $theme->fontSize, FontWeight::Bold)->width,
            );
            foreach ($section->events as $event) {
                $cardWidth = max(
                    $cardWidth,
                    $this->measurer->measureLine($event->label, 0.94 * $theme->fontSize, FontWeight::Bold)->width + 5.0 * $su,
                    $this->measurer->measureLine($event->date, 0.78 * $theme->fontSize, FontWeight::Bold)->width + 4.0 * $su,
                );
            }
        }

        $leftGutter = max(16.0 * $su, $sectionTitleWidth + 5.0 * $su);
        $laneWidth = max(48.0 * $su, $maxEvents * $cardWidth + max(0, $maxEvents - 1) * $eventGap);
        $width = max(680.0, $margin * 2.0 + $leftGutter + $laneWidth);
        $height = max(220.0, $margin * 2.0 + $titleHeight + \count($diagram->sections) * $laneHeight);

        /** @var list<NodeInterface> $nodes */
        $nodes = [];

        $axisStyle = ShapeStyle::stroked($theme->mutedTextColor, max(1.0, 0.9 * $theme->strokeWidth));
        $laneStyle = new ShapeStyle($theme->nodeFillColor, null, opacity: 0.24);
        $cardStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $sectionStyle = new TextStyle($theme->fontFamily, 0.95 * $theme->fontSize, FontWeight::Bold, TextAnchor::End, $theme->textColor);
        $labelStyle = new TextStyle($theme->fontFamily, 0.94 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $dateStyle = new TextStyle($theme->fontFamily, 0.78 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->backgroundColor);

        if (null !== $diagram->title) {
            $titleStyle = new TextStyle($theme->fontFamily, 1.28 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
            $metrics = $this->measurer->measureLine($diagram->title->text, $titleStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($width / 2.0, $margin + $metrics->ascent, $diagram->title->text, $titleStyle);
        }

        $laneStartX = $margin + $leftGutter;
        $laneEndX = $width - $margin;
        $top = $margin + $titleHeight;

        foreach ($diagram->sections as $sectionIndex => $section) {
            $laneTop = $top + $sectionIndex * $laneHeight;
            $axisY = $laneTop + $laneHeight / 2.0;
            $accent = $theme->accentColors[$sectionIndex % \count($theme->accentColors)];

            $nodes[] = new RectNode($margin, $laneTop + 1.2 * $su, $width - 2.0 * $margin, $laneHeight - 2.4 * $su, $laneStyle, 7.0);
            $nodes[] = new LineNode($laneStartX, $axisY, $laneEndX, $axisY, $axisStyle);

            $sectionMetrics = $this->measurer->measureLine($section->title, $sectionStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($laneStartX - 2.0 * $su, $axisY - $sectionMetrics->height / 2.0 + $sectionMetrics->ascent, $section->title, $sectionStyle);

            $eventCount = \count($section->events);
            foreach ($section->events as $eventIndex => $event) {
                $x = 1 === $eventCount
                    ? ($laneStartX + $laneEndX) / 2.0
                    : $laneStartX + $cardWidth / 2.0 + $eventIndex * ($cardWidth + $eventGap);
                $above = 0 === $eventIndex % 2;
                $cardX = $x - $cardWidth / 2.0;
                $cardY = $axisY + ($above ? -$cardHeight - 2.1 * $su : 2.1 * $su);
                $connectorEndY = $above ? $cardY + $cardHeight : $cardY;
                $dateWidth = min($cardWidth - 3.0 * $su, max(8.8 * $su, $this->measurer->measureLine($event->date, $dateStyle->fontSize, FontWeight::Bold)->width + 3.0 * $su));

                $nodes[] = new LineNode($x, min($axisY, $connectorEndY), $x, max($axisY, $connectorEndY), ShapeStyle::stroked($accent, max(1.0, 0.85 * $theme->strokeWidth)));
                $nodes[] = new CircleNode($x, $axisY, 0.8 * $su, new ShapeStyle($theme->backgroundColor, $accent, max(2.0, 1.35 * $theme->strokeWidth)));
                $nodes[] = new RectNode($cardX, $cardY, $cardWidth, $cardHeight, $cardStyle, 6.0);
                $nodes[] = new RectNode($x - $dateWidth / 2.0, $cardY + 1.1 * $su, $dateWidth, $dateHeight, ShapeStyle::filled($accent), 999.0);

                $dateMetrics = $this->measurer->measureLine($event->date, $dateStyle->fontSize, FontWeight::Bold);
                $labelMetrics = $this->measurer->measureLine($event->label, $labelStyle->fontSize, FontWeight::Bold);
                $nodes[] = new TextNode($x, $cardY + 1.1 * $su + ($dateHeight - $dateMetrics->height) / 2.0 + $dateMetrics->ascent, $event->date, $dateStyle);
                $nodes[] = new TextNode($x, $cardY + 5.7 * $su + $labelMetrics->ascent, $event->label, $labelStyle);
            }
        }

        return new Scene($width, $height, $theme->backgroundColor, $nodes, title: $diagram->title?->text);
    }
}
