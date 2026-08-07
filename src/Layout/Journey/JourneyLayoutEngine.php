<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Journey;

use Atelier\Diagram\Journey\JourneyActor;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneySection;
use Atelier\Diagram\Journey\JourneyTask;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Element\Frame;
use Atelier\Layout\Element\Stack;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Value\InsetSpec;

final class JourneyLayoutEngine
{
    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
    }

    public function layout(JourneyDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $titleHeight = null !== $diagram->title ? 4.0 * $su : 0.0;
        $sectionGap = 2.0 * $su;
        $laneHeight = 24.0 * $su;
        $maxTasks = max(1, ...array_map(static fn (JourneySection $section): int => \count($section->tasks), $diagram->sections));
        $cardWidth = max(22.0 * $su, 13.0 * $su);
        $contentWidth = $maxTasks * $cardWidth + max(0, $maxTasks - 1) * 2.0 * $su;
        $width = max(420.0, $contentWidth + 2.0 * $margin);
        $height = max(220.0, 2.0 * $margin + $titleHeight + \count($diagram->sections) * $laneHeight + max(0, \count($diagram->sections) - 1) * $sectionGap);

        $stack = Stack::column('journey.sections')
            ->gap($sectionGap)
            ->padding(InsetSpec::px(0.0))
            ->alignItems(Alignment::Stretch);
        foreach ($diagram->sections as $index => $section) {
            $stack = $stack->add(Frame::preferred('section.'.$index, $contentWidth, $laneHeight, minHeight: $laneHeight));
        }

        $context = new LayoutContext(snapStep: 0.5);
        $result = (new LayoutSolver($context))->solve(
            $stack,
            new Rect($margin, $margin + $titleHeight, $width - 2.0 * $margin, $height - 2.0 * $margin - $titleHeight),
        );

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        if (null !== $diagram->title) {
            $titleStyle = new TextStyle($theme->fontFamily, 1.2 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
            $metrics = $this->measurer->measureLine($diagram->title->text, $titleStyle->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($width / 2.0, $margin + $metrics->ascent, $diagram->title->text, $titleStyle);
        }

        foreach ($diagram->sections as $index => $section) {
            $frame = $result->frameOf('section.'.$index) ?? new Rect($margin, $margin + $titleHeight + $index * ($laneHeight + $sectionGap), $width - 2.0 * $margin, $laneHeight);
            $nodes = [...$nodes, ...$this->sectionNodes($section, $frame, $theme, $context)];
        }

        return new Scene($width, $height, $theme->backgroundColor, $nodes, title: $diagram->title?->text);
    }

    /**
     * @return list<NodeInterface>
     */
    private function sectionNodes(JourneySection $section, Rect $frame, Theme $theme, LayoutContext $context): array
    {
        $su = $theme->spacingUnit;
        $headerHeight = 3.5 * $su;
        $cardGap = 2.0 * $su;
        $cardHeight = $frame->height - $headerHeight - 2.0 * $su;
        $taskCount = \count($section->tasks);
        $cardWidth = ($frame->width - max(0, $taskCount - 1) * $cardGap) / max(1, $taskCount);
        // Lanes sit between canvas and cards: mixing toward the background
        // softens them in the right direction for light and dark themes alike.
        $laneFill = $this->mix($theme->nodeFillColor, $theme->backgroundColor, 0.35);
        $nodes = [
            new RectNode($frame->x, $frame->y, $frame->width, $frame->height, new ShapeStyle($laneFill, $theme->nodeStrokeColor, 0.8), 6.0),
            new LineNode($frame->x, $frame->y + $headerHeight, $frame->right(), $frame->y + $headerHeight, ShapeStyle::stroked($theme->nodeStrokeColor, 0.8)),
        ];

        $sectionStyle = new TextStyle($theme->fontFamily, 1.05 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->textColor);
        $sectionMetrics = $this->measurer->measureLine($section->title, $sectionStyle->fontSize, FontWeight::Bold);
        $nodes[] = new TextNode($frame->x + 1.5 * $su, $frame->y + ($headerHeight - $sectionMetrics->height) / 2.0 + $sectionMetrics->ascent, $section->title, $sectionStyle);

        foreach ($section->tasks as $index => $task) {
            $card = new Rect(
                $frame->x + $index * ($cardWidth + $cardGap),
                $frame->y + $headerHeight + $su,
                $cardWidth,
                $cardHeight,
            );
            $nodes = [...$nodes, ...$this->taskNodes($task, $card, $theme, $context)];
        }

        return $nodes;
    }

    /**
     * @return list<NodeInterface>
     */
    private function taskNodes(JourneyTask $task, Rect $frame, Theme $theme, LayoutContext $context): array
    {
        $su = $theme->spacingUnit;
        $scoreColor = $theme->accentColors[0];
        $nodes = [
            new RectNode($frame->x, $frame->y, $frame->width, $frame->height, new ShapeStyle($this->scoreWash($task->score, $theme), $scoreColor, max(1.0, $theme->strokeWidth)), 5.0),
            new RectNode($frame->right() - 4.0 * $su, $frame->y + $su, 3.0 * $su, 2.5 * $su, ShapeStyle::filled($scoreColor), 999.0),
        ];

        $scoreStyle = new TextStyle($theme->fontFamily, 0.9 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->backgroundColor);
        $scoreMetrics = $this->measurer->measureLine((string) $task->score, $scoreStyle->fontSize, FontWeight::Bold);
        $nodes[] = new TextNode($frame->right() - 2.5 * $su, $frame->y + $su + (2.5 * $su - $scoreMetrics->height) / 2.0 + $scoreMetrics->ascent, (string) $task->score, $scoreStyle);

        $taskStyle = new TextStyle($theme->fontFamily, 0.98 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->textColor);
        $nodes = [...$nodes, ...$this->textBlockNodes(
            'task',
            $task->text,
            new Rect($frame->x + 1.25 * $su, $frame->y + 1.35 * $su, $frame->width - 6.0 * $su, 6.2 * $su),
            0.98 * $theme->fontSize,
            $taskStyle,
            $context,
        )];

        $actors = implode(' + ', array_map(static fn (JourneyActor $actor): string => $actor->name, $task->actors));
        $actorStyle = new TextStyle($theme->fontFamily, 0.82 * $theme->fontSize, FontWeight::Normal, TextAnchor::Start, $theme->mutedTextColor);
        $nodes = [...$nodes, ...$this->textBlockNodes(
            'actors',
            $actors,
            new Rect($frame->x + 1.25 * $su, $frame->bottom() - 5.0 * $su, $frame->width - 2.5 * $su, 3.6 * $su),
            0.82 * $theme->fontSize,
            $actorStyle,
            $context,
        )];

        return $nodes;
    }

    /**
     * @return list<TextNode>
     */
    private function textBlockNodes(string $id, string $text, Rect $rect, float $fontSize, TextStyle $style, LayoutContext $context): array
    {
        $layout = TextBlock::of($id, $text, $fontSize)
            ->lineHeight(1.18)
            ->breakWords()
            ->layout($context, $rect);
        $nodes = [];
        foreach ($layout->lines as $line) {
            $nodes[] = new TextNode($line->frame->x, $line->baseline, $line->text, $style);
        }

        return $nodes;
    }

    /**
     * Score maps to the wash strength of the first accent color, so the
     * intensity scale survives any palette, including grayscale themes.
     * Alpha-hex needs a 6-digit hex accent; other notations fall back to the
     * theme node fill.
     */
    private function scoreWash(int $score, Theme $theme): string
    {
        $accent = $theme->accentColors[0];
        if (1 !== preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            return $theme->nodeFillColor;
        }

        $alphas = ['14', '1f', '2b', '38', '47'];

        return $accent.$alphas[max(1, min(5, $score)) - 1];
    }

    private function mix(string $first, string $second, float $amount): string
    {
        if (1 !== preg_match('/^#([0-9a-f]{6})$/i', $first, $a) || 1 !== preg_match('/^#([0-9a-f]{6})$/i', $second, $b)) {
            return $first;
        }

        $amount = max(0.0, min(1.0, $amount));
        $channels = [];
        for ($i = 0; $i < 3; ++$i) {
            $one = hexdec(substr($a[1], $i * 2, 2));
            $two = hexdec(substr($b[1], $i * 2, 2));
            $channels[] = (int) round($one * (1.0 - $amount) + $two * $amount);
        }

        return \sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
    }
}
