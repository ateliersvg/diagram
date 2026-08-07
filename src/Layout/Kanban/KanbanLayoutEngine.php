<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Kanban;

use Atelier\Diagram\Kanban\KanbanCard;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextLayout;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Track\PlacedTrack;
use Atelier\Layout\Track\TrackGroup;
use Atelier\Layout\Value\Dimension;

final class KanbanLayoutEngine
{
    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
    }

    public function layout(KanbanDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $columnGap = 2.5 * $su;
        $columnWidth = 27.5 * $su;
        $headerHeight = 5.5 * $su;
        $cardGap = 1.4 * $su;
        $cardPadding = 1.5 * $su;
        $titleHeight = null === $diagram->title ? 0.0 : 6.5 * $su;
        $lineHeight = 1.25 * $theme->fontSize;
        $labelWidth = $columnWidth - 2.0 * $cardPadding;
        $context = $this->layoutContext();

        $cardHeights = [];
        $maxCardsHeight = 0.0;
        foreach ($diagram->columns as $column) {
            $columnCardsHeight = 0.0;
            foreach ($column->cards as $card) {
                $idMetrics = $this->measurer->measureLine($card->id, 0.78 * $theme->fontSize, FontWeight::Bold);
                $labelLayout = $this->labelLayout($card->id, $card->label, $labelWidth, $theme->fontSize, $lineHeight, $context);
                $cardHeight = max(
                    8.5 * $su,
                    2.4 * $cardPadding + $idMetrics->height + 0.65 * $cardPadding + $labelLayout->contentHeight,
                );
                $cardHeights[$card->id] = $cardHeight;
                $columnCardsHeight += $cardHeight;
            }
            if ([] !== $column->cards) {
                $columnCardsHeight += (\count($column->cards) - 1) * $cardGap;
            }
            $maxCardsHeight = max($maxCardsHeight, $columnCardsHeight);
        }

        $width = max(360.0, 2.0 * $margin + \count($diagram->columns) * $columnWidth + (\count($diagram->columns) - 1) * $columnGap);
        $height = max(260.0, 2.0 * $margin + $titleHeight + $headerHeight + $maxCardsHeight + 2.0 * $su);

        /** @var list<NodeInterface> $nodes */
        $nodes = [];

        $columnStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth, opacity: 0.72);
        $cardStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $titleStyle = new TextStyle($theme->fontFamily, 1.2 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $columnTextStyle = new TextStyle($theme->fontFamily, 1.0 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->textColor);
        $countStyle = new TextStyle($theme->fontFamily, 0.86 * $theme->fontSize, FontWeight::Bold, TextAnchor::End, $theme->mutedTextColor);
        $idStyle = new TextStyle($theme->fontFamily, 0.78 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->mutedTextColor);
        $labelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Normal, TextAnchor::Start, $theme->textColor);

        $top = $margin;
        if (null !== $diagram->title) {
            $metrics = $this->measurer->measureLine($diagram->title->text, $titleStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($width / 2.0, $top + $metrics->ascent, $diagram->title->text, $titleStyle);
            $top += $titleHeight;
        }

        $laneLayout = $this->laneLayout($diagram, $margin, $top, $width - 2.0 * $margin, $height - $top - $margin, $columnWidth, $columnGap, $headerHeight);
        foreach ($diagram->columns as $columnIndex => $column) {
            $lane = $laneLayout->track($column->id);
            if (!$lane instanceof PlacedTrack) {
                continue;
            }
            $accent = $theme->accentColors[$columnIndex % \count($theme->accentColors)];
            $nodes[] = new RectNode($lane->frame->x, $lane->frame->y, $lane->frame->width, $lane->frame->height, $columnStyle, 7.0);
            $nodes[] = new RectNode($lane->headerFrame->x, $lane->headerFrame->y, $lane->headerFrame->width, $lane->headerFrame->height, new ShapeStyle($accent, null, opacity: 0.16), 7.0);

            $columnMetrics = $this->measurer->measureLine($column->label, $columnTextStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($lane->headerFrame->x + 1.7 * $su, $lane->headerFrame->y + ($lane->headerFrame->height - $columnMetrics->height) / 2.0 + $columnMetrics->ascent, $column->label, $columnTextStyle);

            $countText = (string) \count($column->cards);
            $countMetrics = $this->measurer->measureLine($countText, $countStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($lane->headerFrame->right() - 1.7 * $su, $lane->headerFrame->y + ($lane->headerFrame->height - $countMetrics->height) / 2.0 + $countMetrics->ascent, $countText, $countStyle);

            $cardY = $lane->bodyFrame->y + 1.4 * $su;
            foreach ($column->cards as $card) {
                $cardHeight = $cardHeights[$card->id];
                $nodes = [...$nodes, ...$this->cardNodes($card, $lane->frame->x + 1.25 * $su, $cardY, $lane->frame->width - 2.5 * $su, $cardHeight, $cardPadding, $lineHeight, $cardStyle, $idStyle, $labelStyle, $context)];
                $cardY += $cardHeight + $cardGap;
            }
        }

        return new Scene($width, $height, $theme->backgroundColor, $nodes, title: $diagram->title?->text);
    }

    /**
     * @return list<NodeInterface>
     */
    private function cardNodes(KanbanCard $card, float $x, float $y, float $width, float $height, float $padding, float $lineHeight, ShapeStyle $cardStyle, TextStyle $idStyle, TextStyle $labelStyle, LayoutContext $context): array
    {
        $nodes = [
            new RectNode($x, $y, $width, $height, $cardStyle, 6.0),
        ];

        $idMetrics = $this->measurer->measureLine($card->id, $idStyle->fontSize, FontWeight::Bold);
        $nodes[] = new TextNode($x + $padding, $y + $padding + $idMetrics->ascent, $card->id, $idStyle);

        $labelY = $y + $padding + $idMetrics->height + 0.5 * $padding;
        $labelLayout = $this->labelLayout($card->id, $card->label, $width - 2.0 * $padding, $labelStyle->fontSize, $lineHeight, $context, $x + $padding, $labelY);
        foreach ($labelLayout->lines as $line) {
            $nodes[] = new TextNode($line->frame->x, $line->baseline, $line->text, $labelStyle);
        }

        return $nodes;
    }

    private function labelLayout(string $id, string $text, float $width, float $fontSize, float $lineHeight, LayoutContext $context, float $x = 0.0, float $y = 0.0): TextLayout
    {
        return TextBlock::of('kanban.card.'.$id.'.label', $text, $fontSize)
            ->weight(FontWeight::Normal)
            ->lineHeight($lineHeight / $fontSize)
            ->breakWords()
            ->align(Alignment::Start, Alignment::Start)
            ->layout($context, new Rect($x, $y, $width, 1_000_000.0));
    }

    private function layoutContext(): LayoutContext
    {
        return new LayoutContext(textMeasurer: $this->measurer);
    }

    private function laneLayout(KanbanDiagram $diagram, float $x, float $y, float $width, float $height, float $columnWidth, float $gap, float $headerHeight): \Atelier\Layout\Track\PlacedTrackGroup
    {
        $pack = TrackGroup::horizontal('kanban.columns')
            ->gap($gap)
            ->headerSize($headerHeight);

        foreach ($diagram->columns as $column) {
            $pack = $pack->addTrack($column->id, Dimension::fixed($columnWidth));
        }

        return $pack->place(new Rect($x, $y, $width, $height));
    }
}
