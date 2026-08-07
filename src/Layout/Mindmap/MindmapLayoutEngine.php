<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Mindmap;

use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class MindmapLayoutEngine
{
    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
    }

    public function layout(MindmapDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $columnGap = 7.0 * $su;
        $rowGap = 2.0 * $su;
        $paddingX = 1.75 * $su;
        $nodeHeight = 4.0 * $su;

        $sizes = [];
        $depthWidths = [];
        $maxDepth = 0;
        foreach ($diagram->nodesDepthFirst() as $node) {
            $depth = $this->depthOf($diagram->root, $node->id);
            $maxDepth = max($maxDepth, $depth);
            $fontSize = 0 === $depth ? 1.12 * $theme->fontSize : $theme->fontSize;
            $weight = 0 === $depth ? FontWeight::Bold : FontWeight::Normal;
            $width = max(10.0 * $su, $this->measurer->measureLine($node->label, $fontSize, $weight)->width + 2.0 * $paddingX);
            $sizes[$node->id] = ['width' => $width, 'height' => $nodeHeight, 'depth' => $depth];
            $depthWidths[$depth] = max($depthWidths[$depth] ?? 0.0, $width);
        }

        $subtreeHeights = [];
        $contentHeight = $this->subtreeHeight($diagram->root, $sizes, $subtreeHeights, $rowGap);

        $xByDepth = [];
        $x = $margin;
        for ($depth = 0; $depth <= $maxDepth; ++$depth) {
            $xByDepth[$depth] = $x;
            $x += ($depthWidths[$depth] ?? 0.0) + $columnGap;
        }

        $width = max(420.0, $xByDepth[$maxDepth] + $depthWidths[$maxDepth] + $margin);
        $height = max(260.0, $contentHeight + 2.0 * $margin);

        /** @var array<string, Rect> $frames */
        $frames = [];
        $this->place($diagram->root, $margin, $xByDepth, $sizes, $subtreeHeights, $rowGap, $frames);

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        foreach ($diagram->nodesDepthFirst() as $node) {
            foreach ($node->children as $child) {
                $nodes[] = $this->connector($frames[$node->id], $frames[$child->id], $theme);
            }
        }

        foreach ($diagram->nodesDepthFirst() as $node) {
            $frame = $frames[$node->id];
            $depth = $sizes[$node->id]['depth'];
            $accent = $theme->accentColors[$depth % \count($theme->accentColors)];
            $isRoot = 0 === $depth;
            $style = new ShapeStyle(
                fill: $isRoot ? $accent : $theme->nodeFillColor,
                stroke: $isRoot ? $accent : $theme->nodeStrokeColor,
                strokeWidth: $theme->strokeWidth,
            );
            $textStyle = new TextStyle(
                $theme->fontFamily,
                $isRoot ? 1.12 * $theme->fontSize : $theme->fontSize,
                $isRoot ? FontWeight::Bold : FontWeight::Normal,
                TextAnchor::Middle,
                $isRoot ? $theme->backgroundColor : $theme->textColor,
            );
            $metrics = $this->measurer->measureLine($node->label, $textStyle->fontSize, $textStyle->fontWeight);

            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $style, 1.5 * $theme->spacingUnit);
            $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $frame->y + ($frame->height - $metrics->height) / 2.0 + $metrics->ascent, $node->label, $textStyle);
        }

        return new Scene($width, $height, $theme->backgroundColor, $nodes);
    }

    /**
     * @param array<string, array{width: float, height: float, depth: int}> $sizes
     * @param array<string, float>                                          $subtreeHeights
     */
    private function subtreeHeight(MindmapNode $node, array $sizes, array &$subtreeHeights, float $rowGap): float
    {
        if ([] === $node->children) {
            return $subtreeHeights[$node->id] = $sizes[$node->id]['height'];
        }

        $childrenHeight = 0.0;
        foreach ($node->children as $index => $child) {
            $childrenHeight += $this->subtreeHeight($child, $sizes, $subtreeHeights, $rowGap);
            if ($index > 0) {
                $childrenHeight += $rowGap;
            }
        }

        return $subtreeHeights[$node->id] = max($sizes[$node->id]['height'], $childrenHeight);
    }

    /**
     * @param array<int, float>                                             $xByDepth
     * @param array<string, array{width: float, height: float, depth: int}> $sizes
     * @param array<string, float>                                          $subtreeHeights
     * @param array<string, Rect>                                           $frames
     */
    private function place(MindmapNode $node, float $top, array $xByDepth, array $sizes, array $subtreeHeights, float $rowGap, array &$frames): void
    {
        $size = $sizes[$node->id];
        $subtreeHeight = $subtreeHeights[$node->id];
        $frames[$node->id] = new Rect(
            $xByDepth[$size['depth']],
            $top + ($subtreeHeight - $size['height']) / 2.0,
            $size['width'],
            $size['height'],
        );

        if ([] === $node->children) {
            return;
        }

        $childrenHeight = 0.0;
        foreach ($node->children as $index => $child) {
            $childrenHeight += $subtreeHeights[$child->id];
            if ($index > 0) {
                $childrenHeight += $rowGap;
            }
        }

        $childTop = $top + ($subtreeHeight - $childrenHeight) / 2.0;
        foreach ($node->children as $child) {
            $this->place($child, $childTop, $xByDepth, $sizes, $subtreeHeights, $rowGap, $frames);
            $childTop += $subtreeHeights[$child->id] + $rowGap;
        }
    }

    private function connector(Rect $from, Rect $to, Theme $theme): PathNode
    {
        $sx = $from->right();
        $sy = $from->y + $from->height / 2.0;
        $tx = $to->x;
        $ty = $to->y + $to->height / 2.0;
        $bend = max(16.0, ($tx - $sx) / 2.0);

        return new PathNode(
            PathData::cubic($sx, $sy, $sx + $bend, $sy, $tx - $bend, $ty, $tx, $ty),
            ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth),
        );
    }

    private function depthOf(MindmapNode $node, string $id, int $depth = 0): int
    {
        if ($node->id === $id) {
            return $depth;
        }

        foreach ($node->children as $child) {
            $found = $this->depthOf($child, $id, $depth + 1);
            if ($found >= 0) {
                return $found;
            }
        }

        return -1;
    }
}
