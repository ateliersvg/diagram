<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\State;

use Atelier\Diagram\Layout\Support\ArrowHeadFactory;
use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Layout\Support\TitleArtist;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\OrientedTextNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextLayout;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Lays out a state diagram as a layered graph (Sugiyama-lite).
 *
 * 1. Longest-path layering from the initial/source states; cycles are
 *    broken by ignoring back edges found via DFS.
 * 2. A few barycenter sweeps reduce crossings within ranks.
 * 3. Ranks become rows (TopToBottom) or columns (LeftToRight); nodes are
 *    sized from label metrics + padding and centered per rank.
 * 4. Edges: straight between adjacent ranks, a curved side bow for back
 *    edges and longer spans, small arcs for self-loops; arrowheads are
 *    filled paths; labels sit at edge midpoints over a background halo.
 *
 * Deterministic: vertex declaration order breaks every tie.
 */
final class StateLayoutEngine
{
    private const int ORDERING_SWEEPS = 3;

    private readonly TitleArtist $titles;
    private readonly ArrowHeadFactory $arrowHeads;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->titles = new TitleArtist($this->measurer);
        $this->arrowHeads = new ArrowHeadFactory();
    }

    public function layout(StateDiagram $diagram, Theme $theme): Scene
    {
        $tb = Direction::TopToBottom === $diagram->direction;
        $su = $theme->spacingUnit;

        // 1. Vertices: initial pseudo-state, states in declaration order, final pseudo-state.
        $usesInitial = false;
        $usesFinal = false;
        foreach ($diagram->transitions as $transition) {
            $usesInitial = $usesInitial || StateDiagram::INITIAL === $transition->from;
            $usesFinal = $usesFinal || StateDiagram::FINAL === $transition->to;
        }

        /** @var list<string> $ids */
        $ids = [];
        /** @var array<int, string> $labels vertex index => state label */
        $labels = [];
        if ($usesInitial) {
            $ids[] = StateDiagram::INITIAL;
        }
        foreach ($diagram->states as $state) {
            $labels[\count($ids)] = $state->label;
            $ids[] = $state->id;
        }
        if ($usesFinal) {
            $ids[] = StateDiagram::FINAL;
        }
        $index = array_flip($ids);
        $count = \count($ids);

        // 2. Split transitions into proper edges and self-loops.
        /** @var list<array{0: int, 1: int, 2: string|null}> $edges */
        $edges = [];
        /** @var list<array{0: int, 1: string|null}> $selfLoops */
        $selfLoops = [];
        foreach ($diagram->transitions as $transition) {
            $u = $index[$transition->from];
            $v = $index[$transition->to];
            $label = $transition->label?->text;
            if ($u === $v) {
                $selfLoops[] = [$u, $label];
            } else {
                $edges[] = [$u, $v, $label];
            }
        }

        // 3. Rank.
        $back = $this->findBackEdges($count, $edges);
        $rank = $this->assignRanks($count, $edges, $back);

        // 4. Order within ranks.
        $layers = $this->orderLayers($count, $edges, $rank);

        // 5. Size vertices (flow space: main = flow axis, cross = perpendicular).
        $mainExtent = [];
        $crossExtent = [];
        for ($i = 0; $i < $count; ++$i) {
            if (isset($labels[$i])) {
                $metrics = $this->measurer->measureLine($labels[$i], $theme->fontSize);
                $w = $metrics->width + 5.0 * $su;
                $h = $metrics->height + 3.0 * $su;
                if (null !== $theme->minNodeWidth) {
                    $w = max($w, $theme->minNodeWidth);
                }
                if (null !== $theme->minNodeHeight) {
                    $h = max($h, $theme->minNodeHeight);
                }
            } else {
                // Initial: small filled circle. Final: double circle, slightly larger.
                $w = $h = StateDiagram::INITIAL === $ids[$i] ? 1.5 * $su : 2.0 * $su;
            }
            $mainExtent[$i] = $tb ? $h : $w;
            $crossExtent[$i] = $tb ? $w : $h;
        }

        // 6. Coordinates in flow space.
        $labelFontSize = 0.85 * $theme->fontSize;
        $labelPadX = 1.0 * $theme->spacingUnit;
        $labelPadY = 0.6 * $theme->spacingUnit;
        $labelMainReserve = 0.0;
        $labelContext = new LayoutContext(textMeasurer: $this->measurer);
        foreach ($diagram->transitions as $transition) {
            if (null === $transition->label) {
                continue;
            }
            $layout = $this->edgeLabelLayout('state.transition.reserve', $transition->label->text, $labelFontSize, $theme, $labelContext);
            $labelMainReserve = max($labelMainReserve, $tb ? $layout->contentHeight + 2.0 * $labelPadY : $layout->contentWidth + 2.0 * $labelPadX);
        }

        $rankGap = max(7.0 * $su, $labelMainReserve + 4.0 * $su);
        $nodeGap = 4.0 * $su;
        $rankCrossWidth = [];
        foreach ($layers as $r => $layer) {
            $width = (\count($layer) - 1) * $nodeGap;
            foreach ($layer as $i) {
                $width += $crossExtent[$i];
            }
            $rankCrossWidth[$r] = $width;
        }
        $contentCross = [] === $rankCrossWidth ? 0.0 : max($rankCrossWidth);

        $mainCenter = [];
        $crossCenter = [];
        $mainCursor = 0.0;
        foreach ($layers as $r => $layer) {
            $rankMain = 0.0;
            foreach ($layer as $i) {
                $rankMain = max($rankMain, $mainExtent[$i]);
            }
            $crossCursor = ($contentCross - $rankCrossWidth[$r]) / 2.0;
            foreach ($layer as $i) {
                $mainCenter[$i] = $mainCursor + $rankMain / 2.0;
                $crossCenter[$i] = $crossCursor + $crossExtent[$i] / 2.0;
                $crossCursor += $crossExtent[$i] + $nodeGap;
            }
            $mainCursor += $rankMain + $rankGap;
        }
        $contentMain = $mainCursor - $rankGap;

        // 7. Emit scene nodes.
        $margin = 4.0 * $su;
        $originX = $margin;
        $originY = $margin + $this->titles->blockHeight($diagram->title, $theme);
        $point = static fn (float $main, float $cross): array => $tb
            ? [$originX + $cross, $originY + $main]
            : [$originX + $main, $originY + $cross];

        $edgeStyle = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $arrowLength = 1.25 * $su;
        $arrowHalfWidth = 0.5 * $su;
        $maxCross = $contentCross;

        /** @var list<NodeInterface> $edgeNodes */
        $edgeNodes = [];
        /** @var list<NodeInterface> $labelNodes */
        $labelNodes = [];
        /** @var list<array{0: int, 1: int, 2: string|null}> $backEdges */
        $backEdges = [];

        foreach ($edges as $e => [$u, $v, $label]) {
            if (isset($back[$e]) || $rank[$v] <= $rank[$u]) {
                // Back and same-rank edges are routed last, beyond everything else.
                $backEdges[] = [$u, $v, $label];
                continue;
            }
            if ($rank[$u] + 1 === $rank[$v]) {
                // Adjacent ranks: straight line.
                $s = [$mainCenter[$u] + $mainExtent[$u] / 2.0, $crossCenter[$u]];
                $t = [$mainCenter[$v] - $mainExtent[$v] / 2.0, $crossCenter[$v]];
                [$sx, $sy] = $point($s[0], $s[1]);
                [$tx, $ty] = $point($t[0], $t[1]);
                $edgeNodes[] = new LineNode($sx, $sy, $tx, $ty, $edgeStyle);
                $edgeNodes[] = $this->arrowHeads->arrow($tx, $ty, $tx - $sx, $ty - $sy, $arrowLength, $arrowHalfWidth, $theme->nodeStrokeColor);
                if (null !== $label) {
                    $angle = $tb ? null : rad2deg(atan2($ty - $sy, $tx - $sx));
                    $labelNodes = [...$labelNodes, ...$this->edgeLabel($label, ($sx + $tx) / 2.0, ($sy + $ty) / 2.0, $theme, $angle)];
                }
                continue;
            }
            // Longer span: curve bowing to the cross+ side of the straight line.
            $s = [$mainCenter[$u] + $mainExtent[$u] / 2.0, $crossCenter[$u]];
            $t = [$mainCenter[$v] - $mainExtent[$v] / 2.0, $crossCenter[$v]];
            $bow = max($crossCenter[$u], $crossCenter[$v]) + 4.0 * $su;
            $c1 = [$s[0] + ($t[0] - $s[0]) / 3.0, $bow];
            $c2 = [$s[0] + 2.0 * ($t[0] - $s[0]) / 3.0, $bow];
            $maxCross = max($maxCross, $bow);

            [$sx, $sy] = $point($s[0], $s[1]);
            [$c1x, $c1y] = $point($c1[0], $c1[1]);
            [$c2x, $c2y] = $point($c2[0], $c2[1]);
            [$tx, $ty] = $point($t[0], $t[1]);
            $edgeNodes[] = new PathNode(PathData::cubic($sx, $sy, $c1x, $c1y, $c2x, $c2y, $tx, $ty), $edgeStyle);
            $edgeNodes[] = $this->arrowHeads->arrow($tx, $ty, $tx - $c2x, $ty - $c2y, $arrowLength, $arrowHalfWidth, $theme->nodeStrokeColor);
            if (null !== $label) {
                // Cubic midpoint (t = 0.5).
                $mx = ($sx + 3.0 * $c1x + 3.0 * $c2x + $tx) / 8.0;
                $my = ($sy + 3.0 * $c1y + 3.0 * $c2y + $ty) / 8.0;
                $labelNodes = [...$labelNodes, ...$this->edgeLabel($label, $mx, $my, $theme)];
            }
        }

        // Self-loops: small arc on the node's cross+ side.
        $loopDepth = 4.0 * $su;
        foreach ($selfLoops as [$u, $label]) {
            $mc = $mainCenter[$u];
            $edge = $crossCenter[$u] + $crossExtent[$u] / 2.0;
            $q = $mainExtent[$u] / 4.0;
            [$sx, $sy] = $point($mc - $q, $edge);
            [$c1x, $c1y] = $point($mc - $q - $su, $edge + $loopDepth);
            [$c2x, $c2y] = $point($mc + $q + $su, $edge + $loopDepth);
            [$tx, $ty] = $point($mc + $q, $edge);
            $edgeNodes[] = new PathNode(PathData::cubic($sx, $sy, $c1x, $c1y, $c2x, $c2y, $tx, $ty), $edgeStyle);
            $edgeNodes[] = $this->arrowHeads->arrow($tx, $ty, $tx - $c2x, $ty - $c2y, $arrowLength, $arrowHalfWidth, $theme->nodeStrokeColor);

            $apex = $edge + 0.75 * $loopDepth;
            if (null !== $label) {
                $layout = $this->edgeLabelLayout('state.self.reserve', $label, 0.85 * $theme->fontSize, $theme, $labelContext);
                $labelCrossExtent = ($tb ? $layout->contentWidth : $layout->contentHeight) + $su;
                $labelCross = $apex + $su + $labelCrossExtent / 2.0;
                [$lx, $ly] = $point($mc, $labelCross);
                $labelNodes = [...$labelNodes, ...$this->edgeLabel($label, $lx, $ly, $theme)];
                $maxCross = max($maxCross, $labelCross + $labelCrossExtent / 2.0);
            } else {
                $maxCross = max($maxCross, $edge + $loopDepth);
            }
        }

        // Back and same-rank edges: bow around the cross+ side of all content
        // laid out so far, each one a step further out.
        foreach ($backEdges as [$u, $v, $label]) {
            $s = [$mainCenter[$u], $crossCenter[$u] + $crossExtent[$u] / 2.0];
            $t = [$mainCenter[$v], $crossCenter[$v] + $crossExtent[$v] / 2.0];
            $labelHalf = 0.0;
            if (null !== $label) {
                $layout = $this->edgeLabelLayout('state.back.reserve', $label, 0.85 * $theme->fontSize, $theme, $labelContext);
                $labelHalf = ($tb ? $layout->contentWidth : $layout->contentHeight) / 2.0 + 0.5 * $su;
            }
            // Choose the bow so the curve apex (cubic at t = 0.5) clears the
            // content laid out so far, label included.
            $apex = $maxCross + 3.0 * $su + $labelHalf;
            $bow = (8.0 * $apex - $s[1] - $t[1]) / 6.0;
            $maxCross = $apex + $labelHalf;
            [$sx, $sy] = $point($s[0], $s[1]);
            [$c1x, $c1y] = $point($s[0], $bow);
            [$c2x, $c2y] = $point($t[0], $bow);
            [$tx, $ty] = $point($t[0], $t[1]);
            $edgeNodes[] = new PathNode(PathData::cubic($sx, $sy, $c1x, $c1y, $c2x, $c2y, $tx, $ty), $edgeStyle);
            $edgeNodes[] = $this->arrowHeads->arrow($tx, $ty, $tx - $c2x, $ty - $c2y, $arrowLength, $arrowHalfWidth, $theme->nodeStrokeColor);
            if (null !== $label) {
                [$lx, $ly] = $point(($s[0] + $t[0]) / 2.0, $apex);
                $labelNodes = [...$labelNodes, ...$this->edgeLabel($label, $lx, $ly, $theme)];
            }
        }

        // State shapes and labels.
        $boxStyle = new ShapeStyle(fill: $theme->nodeFillColor, stroke: $theme->nodeStrokeColor, strokeWidth: $theme->strokeWidth);
        $textStyle = new TextStyle($theme->fontFamily, $theme->fontSize, anchor: TextAnchor::Middle, fill: $theme->textColor);
        /** @var list<NodeInterface> $shapeNodes */
        $shapeNodes = [];
        /** @var list<NodeInterface> $textNodes */
        $textNodes = [];
        for ($i = 0; $i < $count; ++$i) {
            [$cx, $cy] = $point($mainCenter[$i], $crossCenter[$i]);
            if (StateDiagram::INITIAL === $ids[$i]) {
                $shapeNodes[] = new CircleNode($cx, $cy, 0.75 * $su, ShapeStyle::filled($theme->nodeStrokeColor));
                continue;
            }
            if (StateDiagram::FINAL === $ids[$i]) {
                $shapeNodes[] = new CircleNode($cx, $cy, $su, new ShapeStyle(fill: $theme->backgroundColor, stroke: $theme->nodeStrokeColor, strokeWidth: $theme->strokeWidth));
                $shapeNodes[] = new CircleNode($cx, $cy, 0.5 * $su, ShapeStyle::filled($theme->nodeStrokeColor));
                continue;
            }
            $w = $tb ? $crossExtent[$i] : $mainExtent[$i];
            $h = $tb ? $mainExtent[$i] : $crossExtent[$i];
            $shapeNodes[] = new RectNode($cx - $w / 2.0, $cy - $h / 2.0, $w, $h, $boxStyle, 0.75 * $su);
            $metrics = $this->measurer->measureLine($labels[$i], $theme->fontSize);
            $textNodes[] = new TextNode($cx, $cy - $metrics->height / 2.0 + $metrics->ascent, $labels[$i], $textStyle);
        }

        // Scene dimensions and optional title.
        $contentWidth = $tb ? $maxCross : $contentMain;
        $contentHeight = $tb ? $contentMain : $maxCross;
        $width = $contentWidth + 2.0 * $margin;
        $height = $originY + $contentHeight + $margin;
        /** @var list<NodeInterface> $titleNodes */
        $titleNodes = [];
        if (null !== $diagram->title) {
            $width = max($width, $this->titles->measure($diagram->title, $theme)->width + 2.0 * $margin);
            $titleNodes[] = $this->titles->node($diagram->title, $theme, $width / 2.0, $margin);
        }

        return new Scene($width, $height, $theme->backgroundColor, [
            ...$titleNodes,
            ...$edgeNodes,
            ...$shapeNodes,
            ...$textNodes,
            ...$labelNodes,
        ], title: $diagram->title?->text);
    }

    /**
     * Finds back edges via DFS: initial/source vertices are visited first,
     * remaining unvisited vertices (pure cycles) in declaration order.
     *
     * @param list<array{0: int, 1: int, 2: string|null}> $edges
     *
     * @return array<int, true> set of back edge indexes
     */
    private function findBackEdges(int $count, array $edges): array
    {
        $out = array_fill(0, $count, []);
        $inDegree = array_fill(0, $count, 0);
        foreach ($edges as $e => [$u, $v]) {
            $out[$u][] = $e;
            ++$inDegree[$v];
        }

        $color = array_fill(0, $count, 0);
        $back = [];
        $visit = function (int $u) use (&$visit, &$color, &$back, $out, $edges): void {
            $color[$u] = 1;
            foreach ($out[$u] as $e) {
                $v = $edges[$e][1];
                if (1 === $color[$v]) {
                    $back[$e] = true;
                } elseif (0 === $color[$v]) {
                    $visit($v);
                }
            }
            $color[$u] = 2;
        };

        for ($i = 0; $i < $count; ++$i) {
            if (0 === $inDegree[$i] && 0 === $color[$i]) {
                $visit($i);
            }
        }
        for ($i = 0; $i < $count; ++$i) {
            if (0 === $color[$i]) {
                $visit($i);
            }
        }

        return $back;
    }

    /**
     * Longest-path layering over the forward (non-back) edges.
     *
     * @param list<array{0: int, 1: int, 2: string|null}> $edges
     * @param array<int, true>                            $back
     *
     * @return array<int, int> vertex index => rank
     */
    private function assignRanks(int $count, array $edges, array $back): array
    {
        $out = array_fill(0, $count, []);
        $inDegree = array_fill(0, $count, 0);
        foreach ($edges as $e => [$u, $v]) {
            if (isset($back[$e])) {
                continue;
            }
            $out[$u][] = $v;
            ++$inDegree[$v];
        }

        $rank = array_fill(0, $count, 0);
        $ready = [];
        for ($i = 0; $i < $count; ++$i) {
            if (0 === $inDegree[$i]) {
                $ready[] = $i;
            }
        }
        while ([] !== $ready) {
            $u = array_shift($ready);
            foreach ($out[$u] as $v) {
                $rank[$v] = max($rank[$v], $rank[$u] + 1);
                if (0 === --$inDegree[$v]) {
                    $ready[] = $v;
                }
            }
        }

        return $rank;
    }

    /**
     * Groups vertices per rank and runs a few barycenter sweeps to reduce
     * crossings. Initial order and all ties follow declaration order.
     *
     * @param list<array{0: int, 1: int, 2: string|null}> $edges
     * @param array<int, int>                             $rank
     *
     * @return array<int, list<int>> rank => ordered vertex indexes
     */
    private function orderLayers(int $count, array $edges, array $rank): array
    {
        $layers = [];
        for ($i = 0; $i < $count; ++$i) {
            $layers[$rank[$i]][] = $i;
        }
        ksort($layers);

        $neighbors = array_fill(0, $count, []);
        foreach ($edges as [$u, $v]) {
            $neighbors[$u][] = $v;
            $neighbors[$v][] = $u;
        }

        $rankKeys = array_keys($layers);
        for ($sweep = 0; $sweep < self::ORDERING_SWEEPS; ++$sweep) {
            foreach ([1, -1] as $step) {
                $ranks = 1 === $step ? $rankKeys : array_reverse($rankKeys);
                foreach ($ranks as $r) {
                    $adjacent = $r - $step;
                    if (!isset($layers[$adjacent])) {
                        continue;
                    }
                    $position = array_flip($layers[$adjacent]);
                    $current = array_flip($layers[$r]);
                    usort($layers[$r], static function (int $a, int $b) use ($neighbors, $position, $current): int {
                        $bary = static function (int $vertex) use ($neighbors, $position, $current): float {
                            $sum = 0.0;
                            $n = 0;
                            foreach ($neighbors[$vertex] as $other) {
                                if (isset($position[$other])) {
                                    $sum += (float) $position[$other];
                                    ++$n;
                                }
                            }

                            return $n > 0 ? $sum / $n : (float) $current[$vertex];
                        };

                        return $bary($a) <=> $bary($b);
                    });
                }
            }
        }

        return $layers;
    }

    /**
     * Builds an edge label centered on (cx, cy). Straight left-to-right edges
     * can use rotated text with an outline; curved/top-down labels keep a halo.
     *
     * @return list<NodeInterface>
     */
    private function edgeLabel(string $text, float $cx, float $cy, Theme $theme, ?float $angleDegrees = null): array
    {
        $fontSize = 0.85 * $theme->fontSize;
        $padX = 1.0 * $theme->spacingUnit;
        $padY = 0.6 * $theme->spacingUnit;
        $textStyle = new TextStyle($theme->fontFamily, $fontSize, anchor: TextAnchor::Middle, fill: $theme->mutedTextColor);
        $context = new LayoutContext(textMeasurer: $this->measurer);
        $layout = $this->edgeLabelLayout('state.transition.label', $text, $fontSize, $theme, $context);
        $frameX = $cx - $layout->frame->width / 2.0;
        $haloX = $cx - $layout->contentWidth / 2.0;
        $y = $cy - $layout->contentHeight / 2.0;

        if (null !== $angleDegrees && 1 === \count($layout->lines)) {
            $line = $layout->lines[0];

            return [
                new OrientedTextNode(
                    $cx,
                    $cy - $layout->contentHeight / 2.0 + ($line->baseline - $layout->frame->y),
                    $line->text,
                    $textStyle,
                    $angleDegrees,
                    $theme->backgroundColor,
                    0.7 * $theme->spacingUnit,
                ),
            ];
        }

        $nodes = [
            new RectNode(
                $haloX - $padX,
                $y - $padY,
                $layout->contentWidth + 2.0 * $padX,
                $layout->contentHeight + 2.0 * $padY,
                ShapeStyle::filled($theme->backgroundColor),
                cornerRadius: 0.45 * $theme->spacingUnit,
            ),
        ];
        $rendered = $this->edgeLabelLayout('state.transition.label.rendered', $text, $fontSize, $theme, $context, $frameX, $y);
        foreach ($rendered->lines as $line) {
            $nodes[] = new TextNode($line->frame->x + $line->frame->width / 2.0, $line->baseline, $line->text, $textStyle);
        }

        return $nodes;
    }

    private function edgeLabelLayout(string $id, string $text, float $fontSize, Theme $theme, LayoutContext $context, float $x = 0.0, float $y = 0.0): TextLayout
    {
        return TextBlock::of($id, $text, $fontSize)
            ->weight(FontWeight::Normal)
            ->lineHeight(1.16)
            ->breakWords()
            ->align(Alignment::Center, Alignment::Start)
            ->layout($context, new Rect($x, $y, 16.0 * $theme->spacingUnit, 1_000_000.0));
    }
}
