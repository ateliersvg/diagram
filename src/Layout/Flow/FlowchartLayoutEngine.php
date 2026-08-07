<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Flow;

use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Layout\Support\ArrowHeadFactory;
use Atelier\Diagram\Layout\Support\ConnectionLabelArtist;
use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Layout\Support\TitleArtist;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Connection\OrthogonalConnection;
use Atelier\Layout\Connection\OrthogonalConnector;
use Atelier\Layout\Connection\Port;
use Atelier\Layout\Connection\PortSide;
use Atelier\Layout\Element\Frame;
use Atelier\Layout\Element\Grid;
use Atelier\Layout\Geometry\Bounds;
use Atelier\Layout\Geometry\Insets;
use Atelier\Layout\Geometry\Point;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Lays out the v0 flowchart subset as ranked boxes and straight edges.
 *
 * The ranking is intentionally small: longest-path assignment for acyclic
 * graphs, with cycles settling into the last touched ranks. It is enough
 * for the first parser/layout integration tests; a real graph layout can
 * replace this engine later without changing the model.
 */
final class FlowchartLayoutEngine
{
    private readonly TitleArtist $titles;

    private readonly ArrowHeadFactory $arrowHeads;

    private readonly ConnectionLabelArtist $connectionLabels;

    private readonly OrthogonalConnector $connector;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->titles = new TitleArtist($this->measurer);
        $this->arrowHeads = new ArrowHeadFactory();
        $this->connectionLabels = new ConnectionLabelArtist($this->measurer);
        $this->connector = new OrthogonalConnector();
    }

    public function layout(Flowchart $flowchart, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $hasEdgeLabels = [] !== array_filter($flowchart->edges, static fn ($edge): bool => null !== $edge->label);
        $rankGap = ($hasEdgeLabels ? 10.0 : 7.0) * $su;
        $nodeGap = ($hasEdgeLabels ? 4.5 : 3.0) * $su;
        $titleHeight = $this->titles->blockHeight($flowchart->title, $theme);
        $leftToRight = Direction::LeftToRight === $flowchart->direction;

        /** @var array<string, array{w: float, h: float}> $sizes */
        $sizes = [];
        foreach ($flowchart->nodes as $node) {
            $metrics = $this->measurer->measureLine($node->label, $theme->fontSize);
            $sizes[$node->id] = [
                'w' => max(10.0 * $su, $metrics->width + 5.0 * $su, $theme->minNodeWidth ?? 0.0),
                'h' => max($metrics->height + 3.0 * $su, $theme->minNodeHeight ?? 0.0),
            ];
        }

        $ranks = $this->ranks($flowchart);
        $ranked = $this->groupByRank($flowchart, $ranks);
        $rankCount = \count($ranked);
        $rankMaxWidth = [];
        $rankMaxHeight = [];
        $rankSumWidth = [];
        $rankSumHeight = [];
        foreach ($ranked as $rank => $ids) {
            $rankMaxWidth[$rank] = 0.0;
            $rankMaxHeight[$rank] = 0.0;
            $rankSumWidth[$rank] = max(0, \count($ids) - 1) * $nodeGap;
            $rankSumHeight[$rank] = max(0, \count($ids) - 1) * $nodeGap;
            foreach ($ids as $id) {
                $rankMaxWidth[$rank] = max($rankMaxWidth[$rank], $sizes[$id]['w']);
                $rankMaxHeight[$rank] = max($rankMaxHeight[$rank], $sizes[$id]['h']);
                $rankSumWidth[$rank] += $sizes[$id]['w'];
                $rankSumHeight[$rank] += $sizes[$id]['h'];
            }
        }

        if ($leftToRight) {
            $contentWidth = array_sum($rankMaxWidth) + max(0, $rankCount - 1) * $rankGap;
            $contentHeight = max(1.0, max($rankSumHeight));
        } else {
            $contentWidth = max(1.0, max($rankSumWidth));
            $contentHeight = array_sum($rankMaxHeight) + max(0, $rankCount - 1) * $rankGap;
        }

        $clusterLabelHeadroom = [] === $flowchart->subgraphs ? 0.0 : 2.0 * $su;
        $width = max(360.0, $contentWidth + 2.0 * $margin);
        $height = max(220.0, $contentHeight + 2.0 * $margin + $titleHeight + $clusterLabelHeadroom);
        $contentLeft = ($width - $contentWidth) / 2.0;
        $contentTop = $margin + $titleHeight + $clusterLabelHeadroom;

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        if (null !== $flowchart->title) {
            $nodes[] = $this->titles->node($flowchart->title, $theme, $width / 2.0, $margin);
        }

        /** @var array<string, Rect> $frames */
        $frames = [];
        $solver = new LayoutSolver(new LayoutContext(snapStep: 0.5));
        foreach ($ranked as $rank => $ids) {
            if ($leftToRight) {
                $x = $contentLeft + array_sum(\array_slice($rankMaxWidth, 0, $rank)) + $rank * $rankGap;
                $grid = Grid::columns('rank.'.$rank, 1)
                    ->gap(0.0, $nodeGap)
                    ->align(Alignment::Center, Alignment::Center);
                foreach ($ids as $id) {
                    $grid = $grid->add(Frame::preferred('node.'.$id, $sizes[$id]['w'], $sizes[$id]['h']));
                }
                $result = $solver->solve($grid, new Rect($x, $contentTop, $rankMaxWidth[$rank], $contentHeight));
            } else {
                $y = $contentTop + array_sum(\array_slice($rankMaxHeight, 0, $rank)) + $rank * $rankGap;
                $grid = Grid::columns('rank.'.$rank, \count($ids))
                    ->gap($nodeGap)
                    ->align(Alignment::Center, Alignment::Center);
                foreach ($ids as $id) {
                    $grid = $grid->add(Frame::preferred('node.'.$id, $sizes[$id]['w'], $sizes[$id]['h']));
                }
                $result = $solver->solve($grid, new Rect($contentLeft, $y, $contentWidth, $rankMaxHeight[$rank]));
            }

            foreach ($ids as $id) {
                $frame = $result->frameOf('node.'.$id);
                if (null !== $frame) {
                    $frames[$id] = $frame;
                }
            }
        }

        $clusterStyle = new ShapeStyle($theme->backgroundColor, $theme->mutedTextColor, $theme->strokeWidth, opacity: 0.85);
        $clusterTextStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->mutedTextColor);
        $labelAvoidRects = $frames;
        /** @var list<NodeInterface> $clusterLabelNodes */
        $clusterLabelNodes = [];
        $subgraphs = $flowchart->subgraphs;
        usort($subgraphs, static fn ($a, $b): int => $a->depth <=> $b->depth);
        foreach ($subgraphs as $subgraph) {
            $cluster = $this->clusterFrame($subgraph->nodeIds, $frames, $su);
            $nodes[] = new RectNode($cluster->x, $cluster->y, $cluster->width, $cluster->height, $clusterStyle, 6.0);
            $metrics = $this->measurer->measureLine($subgraph->label, $clusterTextStyle->fontSize, FontWeight::Bold);
            $labelPaddingX = 0.75 * $su;
            $labelPaddingY = 0.3 * $su;
            $labelFrame = new Rect(
                $cluster->x + $su - $labelPaddingX,
                $cluster->y + 0.65 * $su,
                $metrics->width + 2.0 * $labelPaddingX,
                $metrics->height + 2.0 * $labelPaddingY,
            );
            $labelAvoidRects['subgraph.'.$subgraph->id.'.label'] = $labelFrame;
            $clusterLabelNodes[] = new RectNode($labelFrame->x, $labelFrame->y, $labelFrame->width, $labelFrame->height, ShapeStyle::filled($theme->backgroundColor), 0.4 * $su);
            $clusterLabelNodes[] = new TextNode($labelFrame->x + $labelPaddingX, $labelFrame->y + $labelPaddingY + $metrics->ascent, $subgraph->label, $clusterTextStyle);
        }

        $edgeStyle = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $labelStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
        $labelAvoidIndex = RectIndex::from($labelAvoidRects);
        /** @var list<NodeInterface> $edgeLabelNodes */
        $edgeLabelNodes = [];
        foreach ($flowchart->edges as $edge) {
            $connection = $this->connectionAvoidingIntermediateNodes($edge->from, $edge->to, $frames, $su, $leftToRight);
            $start = $connection->startPoint();
            $end = $connection->endPoint();
            if ($connection->isStraight()) {
                $nodes[] = new LineNode($start->x, $start->y, $end->x, $end->y, $edgeStyle);
            } else {
                $nodes[] = new PathNode(PathData::connection($connection), $edgeStyle);
            }
            $nodes[] = $this->arrowHeads->arrow($end->x, $end->y, $connection->tipTangent->x, $connection->tipTangent->y, 1.15 * $su, 0.45 * $su, $theme->nodeStrokeColor);

            if (null !== $edge->label) {
                $edgeLabelNodes = [
                    ...$edgeLabelNodes,
                    ...$this->connectionLabels->nodes($edge->label->text, $connection, $labelAvoidIndex, $theme, $labelStyle),
                ];
            }
        }

        $nodeStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $textStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
        foreach ($flowchart->nodes as $node) {
            $frame = $frames[$node->id];
            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $nodeStyle, 4.0);
            $metrics = $this->measurer->measureLine($node->label, $theme->fontSize);
            $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $frame->y + ($frame->height - $metrics->height) / 2.0 + $metrics->ascent, $node->label, $textStyle);
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$clusterLabelNodes, ...$edgeLabelNodes], title: $flowchart->title?->text);
    }

    /**
     * @param non-empty-list<string> $nodeIds
     * @param array<string, Rect>    $frames
     */
    private function clusterFrame(array $nodeIds, array $frames, float $su): Rect
    {
        // Every subgraph node id is a placed node (Flowchart's constructor
        // rejects unknown ids) and nodeIds is non-empty, so both lookups below
        // always succeed. The throws assert that invariant loudly rather than
        // silently dropping the cluster if it is ever violated.
        $rects = array_map(
            static fn (string $nodeId): Rect => $frames[$nodeId]
                ?? throw new RuntimeException(\sprintf('Flowchart subgraph references unplaced node "%s".', $nodeId)),
            $nodeIds,
        );

        $bounds = Bounds::fromRects($rects)
            ?? throw new RuntimeException('Flowchart cluster bounds are empty for a non-empty subgraph.');

        $padding = 1.5 * $su;
        $labelReserve = 4.5 * $su;
        $cluster = Bounds::expand($bounds, new Insets($labelReserve, $padding, $padding, $padding));

        return new Rect(
            max(0.0, $cluster->x),
            max(0.0, $cluster->y),
            $cluster->width,
            $cluster->height,
        );
    }

    /**
     * @param array<string, Rect> $frames
     */
    private function connectionAvoidingIntermediateNodes(string $fromId, string $toId, array $frames, float $su, bool $leftToRight): OrthogonalConnection
    {
        $from = $frames[$fromId];
        $to = $frames[$toId];
        $connection = $this->connector->connect($from, $to);
        if (!$connection->isStraight()) {
            return $connection;
        }

        $blockers = [];
        foreach ($frames as $id => $frame) {
            if ($fromId === $id || $toId === $id) {
                continue;
            }
            if ($this->straightConnectionIntersects($connection, $frame, 0.5 * $su)) {
                $blockers[] = $frame;
            }
        }

        if ([] === $blockers) {
            return $connection;
        }

        if ($leftToRight) {
            $startSide = $to->x >= $from->x ? PortSide::Right : PortSide::Left;
            $endSide = $to->x >= $from->x ? PortSide::Left : PortSide::Right;
            $start = Port::on($from, $startSide);
            $end = Port::on($to, $endSide);
            $offsetY = max(array_map(static fn (Rect $rect): float => $rect->y + $rect->height, $blockers)) + 2.0 * $su;
            $points = [
                $start->point,
                new Point($start->point->x, $offsetY),
                new Point($end->point->x, $offsetY),
                $end->point,
            ];

            return new OrthogonalConnection(
                $start,
                $end,
                $points,
                OrthogonalConnection::segmentsForPoints($points),
                new Point(($start->point->x + $end->point->x) / 2.0, $offsetY),
                new Point($end->point->x - $end->point->x, $end->point->y - $offsetY),
            );
        }

        $startSide = $to->y >= $from->y ? PortSide::Bottom : PortSide::Top;
        $endSide = $to->y >= $from->y ? PortSide::Top : PortSide::Bottom;
        $start = Port::on($from, $startSide);
        $end = Port::on($to, $endSide);
        $offsetX = max(array_map(static fn (Rect $rect): float => $rect->x + $rect->width, $blockers)) + 2.0 * $su;
        $points = [
            $start->point,
            new Point($offsetX, $start->point->y),
            new Point($offsetX, $end->point->y),
            $end->point,
        ];

        return new OrthogonalConnection(
            $start,
            $end,
            $points,
            OrthogonalConnection::segmentsForPoints($points),
            new Point($offsetX, ($start->point->y + $end->point->y) / 2.0),
            new Point($end->point->x - $offsetX, $end->point->y - $end->point->y),
        );
    }

    private function straightConnectionIntersects(OrthogonalConnection $connection, Rect $rect, float $padding): bool
    {
        $segment = $connection->firstSegment();
        if ($segment->isVertical()) {
            $minY = min($segment->start->y, $segment->end->y);
            $maxY = max($segment->start->y, $segment->end->y);

            return $segment->start->x >= $rect->x - $padding
                && $segment->start->x <= $rect->x + $rect->width + $padding
                && $maxY >= $rect->y - $padding
                && $minY <= $rect->y + $rect->height + $padding;
        }

        $minX = min($segment->start->x, $segment->end->x);
        $maxX = max($segment->start->x, $segment->end->x);

        return $segment->start->y >= $rect->y - $padding
            && $segment->start->y <= $rect->y + $rect->height + $padding
            && $maxX >= $rect->x - $padding
            && $minX <= $rect->x + $rect->width + $padding;
    }

    /**
     * @return array<string, int>
     */
    private function ranks(Flowchart $flowchart): array
    {
        $ranks = [];
        foreach ($flowchart->nodes as $node) {
            $ranks[$node->id] = 0;
        }

        for ($i = 0; $i < \count($flowchart->nodes); ++$i) {
            foreach ($flowchart->edges as $edge) {
                if ($edge->from === $edge->to) {
                    continue;
                }
                $ranks[$edge->to] = max($ranks[$edge->to], min(\count($flowchart->nodes) - 1, $ranks[$edge->from] + 1));
            }
        }

        return $ranks;
    }

    /**
     * @param array<string, int> $ranks
     *
     * @return non-empty-list<list<string>>
     */
    private function groupByRank(Flowchart $flowchart, array $ranks): array
    {
        $grouped = [];
        foreach ($flowchart->nodes as $node) {
            $grouped[$ranks[$node->id]][] = $node->id;
        }
        ksort($grouped);

        return array_values($grouped);
    }
}
