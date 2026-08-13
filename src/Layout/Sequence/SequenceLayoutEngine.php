<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Sequence;

use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Layout\Support\TitleArtist;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\Participant;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Constraint\BoxConstraints;
use Atelier\Layout\Element\Frame;
use Atelier\Layout\Element\Grid;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Grid\TrackSize;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextLayout;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Lays out a sequence diagram as participant tracks and message rows.
 *
 * Participant columns are solved through atelier/layout Grid tracks. The
 * diagram engine then consumes those solved frames to place lifelines,
 * message lines, labels and arrowheads. This deliberately exercises the
 * shared layout package without making it responsible for SVG-specific
 * routing yet.
 */
final class SequenceLayoutEngine
{
    private readonly TitleArtist $titles;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->titles = new TitleArtist($this->measurer);
    }

    public function layout(SequenceDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $fontSize = $theme->fontSize;
        $participantCount = \count($diagram->participants);
        $rowCount = max(1, \count($diagram->messages));
        $margin = 4.0 * $su;
        $gap = 4.0 * $su;
        $headerHeight = $this->headerHeight($diagram->participants, $theme);
        $messageTopGap = 3.0 * $su;
        $messageBottomGap = 2.5 * $su;
        $titleHeight = $this->titles->blockHeight($diagram->title, $theme);
        $minTrackWidth = $this->minTrackWidth($diagram->participants, $theme);
        $messageLabelWidth = 24.0 * $su;
        $messageTextStyle = new TextStyle($theme->fontFamily, 0.88 * $fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
        $context = new LayoutContext(textMeasurer: $this->measurer);
        $messageRowHeights = [];
        foreach ($diagram->messages as $index => $message) {
            $labelLayout = $this->wrappedTextLayout('sequence.message.'.$index, $message->label, $messageLabelWidth, $messageTextStyle->fontSize, FontWeight::Normal, 1.16, $context);
            $messageRowHeights[$index] = max(4.4 * $su, $labelLayout->contentHeight + 2.8 * $su);
        }

        // Fragment and branch labels occupy a header band before the first
        // message in their range. Without that reserve, labels such as
        // "loop status polling" and "Show status" are painted on top of one
        // another even though their individual text metrics are correct.
        $messageRowHeaderReserves = array_fill(0, $rowCount, 0.0);
        foreach ($diagram->blocks as $block) {
            $messageRowHeaderReserves[$block->firstMessageIndex] = max(
                $messageRowHeaderReserves[$block->firstMessageIndex] ?? 0.0,
                2.75 * $su,
            );
            foreach ($block->branches as $branch) {
                if ($branch->firstMessageIndex === $block->firstMessageIndex) {
                    continue;
                }
                $messageRowHeaderReserves[$branch->firstMessageIndex] = max(
                    $messageRowHeaderReserves[$branch->firstMessageIndex] ?? 0.0,
                    2.5 * $su,
                );
            }
        }
        foreach ($messageRowHeights as $index => $rowHeight) {
            $messageRowHeights[$index] = $rowHeight + ($messageRowHeaderReserves[$index] ?? 0.0);
        }
        $messageRowsHeight = [] === $messageRowHeights ? 4.4 * $su : array_sum($messageRowHeights);
        $messageLabelReserve = $this->messageLabelReserve($diagram, $theme, $messageLabelWidth, $context);

        $width = max(
            360.0,
            $margin * 2.0 + $participantCount * $minTrackWidth + max(0, $participantCount - 1) * $gap,
            $margin * 2.0 + $messageLabelReserve,
        );
        $height = $margin + $titleHeight + $headerHeight + $messageTopGap + $messageRowsHeight + $messageBottomGap + $margin;

        $contentTop = $margin + $titleHeight;
        $grid = Grid::tracks('participants', array_fill(0, $participantCount, TrackSize::fr()))
            ->gap($gap)
            ->align(Alignment::Stretch, Alignment::Stretch);
        foreach ($diagram->participants as $participant) {
            $grid = $grid->add(new Frame('participant.'.$participant->id, new BoxConstraints(minWidth: $minTrackWidth, minHeight: $headerHeight)));
        }

        $layout = (new LayoutSolver(new LayoutContext(snapStep: 0.5)))->solve(
            $grid,
            new Rect($margin, $contentTop, $width - $margin * 2.0, $headerHeight),
        );

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        if (null !== $diagram->title) {
            $nodes[] = $this->titles->node($diagram->title, $theme, $width / 2.0, $margin);
        }

        $textStyle = new TextStyle($theme->fontFamily, $fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
        $headerStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $lifelineStyle = new ShapeStyle(null, $theme->mutedTextColor, $theme->strokeWidth, LineStyle::Dotted, 0.75);
        $messageStyle = new ShapeStyle(null, $theme->nodeStrokeColor, $theme->strokeWidth);

        /** @var array<string, float> $laneCenters */
        $laneCenters = [];
        $lifelineTop = $contentTop + $headerHeight;
        $lifelineBottom = $height - $margin;
        $messageCenters = [];
        $messageTopEdges = [];
        $messageBottomEdges = [];
        $messageCursor = $lifelineTop + $messageTopGap;
        for ($index = 0; $index < $rowCount; ++$index) {
            $rowHeight = $messageRowHeights[$index] ?? 4.4 * $su;
            $headerReserve = $messageRowHeaderReserves[$index] ?? 0.0;
            $messageTopEdges[$index] = $messageCursor;
            $messageCenters[$index] = $messageCursor + $headerReserve + ($rowHeight - $headerReserve) / 2.0;
            $messageBottomEdges[$index] = $messageCursor + $rowHeight;
            $messageCursor += $rowHeight;
        }
        foreach ($diagram->participants as $participant) {
            $frame = $layout->frameOf('participant.'.$participant->id);
            if (null === $frame) {
                continue;
            }

            $cx = $frame->x + $frame->width / 2.0;
            $laneCenters[$participant->id] = $cx;
            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $headerStyle, 4.0);

            $metrics = $this->measurer->measureLine($participant->label, $fontSize);
            $nodes[] = new TextNode(
                $cx,
                $frame->y + ($frame->height - $metrics->height) / 2.0 + $metrics->ascent,
                $participant->label,
                $textStyle,
            );
            $nodes[] = new LineNode($cx, $lifelineTop, $cx, $lifelineBottom, $lifelineStyle);
        }

        // Block frames are outlines only: an opaque fill would cover lifelines
        // and clash with any theme whose canvas is not light.
        $blockStyle = new ShapeStyle(null, $theme->mutedTextColor, $theme->strokeWidth, opacity: 0.85);
        $blockTextStyle = new TextStyle($theme->fontFamily, 0.82 * $fontSize, FontWeight::Bold, TextAnchor::Start, $theme->mutedTextColor);
        foreach ($diagram->blocks as $block) {
            $top = $messageTopEdges[$block->firstMessageIndex] ?? $lifelineTop + $messageTopGap;
            $bottom = ($messageBottomEdges[$block->lastMessageIndex] ?? $top + 4.4 * $su) + 1.2 * $su;
            $nodes[] = new RectNode($margin + $su, $top, $width - 2.0 * ($margin + $su), $bottom - $top, $blockStyle, 6.0);
            $label = $block->kind->value.' '.$block->label;
            $metrics = $this->measurer->measureLine($label, $blockTextStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($margin + 2.0 * $su, $top + 0.6 * $su + $metrics->ascent, $label, $blockTextStyle);

            foreach ($block->branches as $branch) {
                if ($branch->firstMessageIndex === $block->firstMessageIndex) {
                    continue;
                }
                $separatorY = ($messageTopEdges[$branch->firstMessageIndex] ?? $top) + 0.9 * $su;
                $nodes[] = new LineNode(
                    $margin + 1.5 * $su,
                    $separatorY,
                    $width - $margin - 1.5 * $su,
                    $separatorY,
                    new ShapeStyle(null, $theme->mutedTextColor, max(1.0, 0.75 * $theme->strokeWidth), LineStyle::Dashed),
                );
                $branchLabel = $branch->label;
                $branchMetrics = $this->measurer->measureLine($branchLabel, $blockTextStyle->fontSize, FontWeight::Bold);
                $nodes[] = new RectNode(
                    $margin + 2.0 * $su - 0.4 * $su,
                    $separatorY - $branchMetrics->height / 2.0,
                    $branchMetrics->width + 0.8 * $su,
                    $branchMetrics->height,
                    ShapeStyle::filled($theme->backgroundColor),
                );
                $nodes[] = new TextNode($margin + 2.0 * $su, $separatorY - $branchMetrics->height / 2.0 + $branchMetrics->ascent, $branchLabel, $blockTextStyle);
            }
        }

        foreach ($diagram->activations as $activation) {
            $x = $laneCenters[$activation->participant] ?? null;
            if (null === $x) {
                continue;
            }
            $top = ($messageCenters[$activation->firstMessageIndex] ?? $lifelineTop + $messageTopGap) + 0.75 * $su;
            $bottom = ($messageCenters[$activation->lastMessageIndex] ?? $top) + 2.0 * $su;
            $nodes[] = new RectNode(
                $x - 0.45 * $su,
                $top,
                0.9 * $su,
                max(1.25 * $su, $bottom - $top),
                new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, max(1.0, 0.75 * $theme->strokeWidth)),
                2.0,
            );
        }

        foreach ($diagram->messages as $index => $message) {
            $y = $messageCenters[$index] ?? $lifelineTop + $messageTopGap;
            $style = MessageArrow::Dashed === $message->arrow
                ? new ShapeStyle(null, $theme->nodeStrokeColor, $theme->strokeWidth, LineStyle::Dashed)
                : $messageStyle;

            $fromX = $laneCenters[$message->from] ?? $margin;
            $toX = $laneCenters[$message->to] ?? $width - $margin;

            if ($fromX === $toX) {
                $nodes = [...$nodes, ...$this->selfMessage($fromX, $y, $su, $style, $theme)];
                $labelX = min($width - $margin, $fromX + 3.0 * $su);
            } else {
                $nodes[] = new LineNode($fromX, $y, $toX, $y, $style);
                $nodes[] = $this->arrowHead($toX, $y, $toX > $fromX ? 1.0 : -1.0, $theme);
                $labelX = ($fromX + $toX) / 2.0;
            }

            $nodes = [...$nodes, ...$this->messageLabelNodes($index, $message->label, $labelX, $y, $messageLabelWidth, $theme, $messageTextStyle, $context)];
        }

        return new Scene($width, $height, $theme->backgroundColor, $nodes, title: $diagram->title?->text);
    }

    /**
     * @param list<Participant> $participants
     */
    private function headerHeight(array $participants, Theme $theme): float
    {
        $height = 0.0;
        foreach ($participants as $participant) {
            $height = max($height, $this->measurer->measureLine($participant->label, $theme->fontSize)->height);
        }

        return $height + 3.0 * $theme->spacingUnit;
    }

    /**
     * @param list<Participant> $participants
     */
    private function minTrackWidth(array $participants, Theme $theme): float
    {
        $width = 10.0 * $theme->spacingUnit;
        foreach ($participants as $participant) {
            $width = max($width, $this->measurer->measureLine($participant->label, $theme->fontSize)->width + 4.0 * $theme->spacingUnit);
        }

        return $width;
    }

    private function messageLabelReserve(SequenceDiagram $diagram, Theme $theme, float $maxWidth, LayoutContext $context): float
    {
        $width = 0.0;
        foreach ($diagram->messages as $message) {
            $layout = $this->wrappedTextLayout('sequence.message.reserve', $message->label, $maxWidth, 0.88 * $theme->fontSize, FontWeight::Normal, 1.16, $context);
            $width = max($width, $layout->contentWidth + 2.0 * $theme->spacingUnit);
        }

        return $width;
    }

    /**
     * @return list<NodeInterface>
     */
    private function messageLabelNodes(int $index, string $text, float $centerX, float $arrowY, float $maxWidth, Theme $theme, TextStyle $style, LayoutContext $context): array
    {
        $su = $theme->spacingUnit;
        $layout = $this->wrappedTextLayout('sequence.message.'.$index.'.label', $text, $maxWidth, $style->fontSize, $style->fontWeight, 1.16, $context);
        $x = $centerX - $layout->contentWidth / 2.0;
        $y = $arrowY - $layout->contentHeight - 0.65 * $su;
        $rendered = $this->wrappedTextLayout('sequence.message.'.$index.'.rendered', $text, $layout->contentWidth, $style->fontSize, $style->fontWeight, 1.16, $context, $x, $y);
        $nodes = [
            new RectNode(
                $x - 0.65 * $su,
                $y - 0.3 * $su,
                $layout->contentWidth + 1.3 * $su,
                $layout->contentHeight + 0.6 * $su,
                ShapeStyle::filled($theme->backgroundColor),
                0.35 * $su,
            ),
        ];
        foreach ($rendered->lines as $line) {
            $nodes[] = new TextNode($line->frame->x + $line->frame->width / 2.0, $line->baseline, $line->text, $style);
        }

        return $nodes;
    }

    private function wrappedTextLayout(string $id, string $text, float $width, float $fontSize, FontWeight $weight, float $lineHeight, LayoutContext $context, float $x = 0.0, float $y = 0.0): TextLayout
    {
        return TextBlock::of($id, $text, $fontSize)
            ->weight($weight)
            ->lineHeight($lineHeight)
            ->breakWords()
            ->align(Alignment::Center, Alignment::Start)
            ->layout($context, new Rect($x, $y, $width, 1_000_000.0));
    }

    /**
     * @return list<NodeInterface>
     */
    private function selfMessage(float $x, float $y, float $su, ShapeStyle $style, Theme $theme): array
    {
        $loopWidth = 4.0 * $su;
        $loopHeight = 1.6 * $su;
        $path = \sprintf(
            'M %s %s H %s V %s H %s',
            PathData::number($x),
            PathData::number($y),
            PathData::number($x + $loopWidth),
            PathData::number($y + $loopHeight),
            PathData::number($x),
        );

        return [
            new PathNode($path, $style),
            $this->arrowHead($x, $y + $loopHeight, -1.0, $theme),
        ];
    }

    private function arrowHead(float $x, float $y, float $direction, Theme $theme): PathNode
    {
        $length = 1.15 * $theme->spacingUnit;
        $half = 0.45 * $theme->spacingUnit;
        $backX = $x - $direction * $length;
        $path = \sprintf(
            'M %s %s L %s %s L %s %s Z',
            PathData::number($x),
            PathData::number($y),
            PathData::number($backX),
            PathData::number($y - $half),
            PathData::number($backX),
            PathData::number($y + $half),
        );

        return new PathNode($path, ShapeStyle::filled($theme->nodeStrokeColor));
    }
}
