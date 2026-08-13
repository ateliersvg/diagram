<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Block;

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockRelationship;
use Atelier\Diagram\Layout\Support\ArrowHeadFactory;
use Atelier\Diagram\Layout\Support\ConnectionLabelArtist;
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
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Alignment;
use Atelier\Layout\Connection\OrthogonalConnector;
use Atelier\Layout\Element\Frame;
use Atelier\Layout\Element\Grid;
use Atelier\Layout\Geometry\Bounds;
use Atelier\Layout\Geometry\Insets;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class BlockLayoutEngine
{
    private readonly TitleArtist $titles;

    private readonly OrthogonalConnector $connector;

    private readonly ArrowHeadFactory $arrowHeads;

    private readonly ConnectionLabelArtist $connectionLabels;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->titles = new TitleArtist($this->measurer);
        $this->connector = new OrthogonalConnector();
        $this->arrowHeads = new ArrowHeadFactory();
        $this->connectionLabels = new ConnectionLabelArtist($this->measurer);
    }

    public function layout(BlockDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $hasRelationshipLabels = [] !== array_filter($diagram->relationships, static fn (BlockRelationship $relationship): bool => null !== $relationship->label);
        $gap = ($hasRelationshipLabels ? 16.0 : 5.0) * $su;
        $titleHeight = $this->titles->blockHeight($diagram->title, $theme);

        $sizes = [];
        foreach ($diagram->nodes as $node) {
            $labelMetrics = $this->measurer->measureLine($node->label, $theme->fontSize, FontWeight::Bold);
            $idMetrics = $this->measurer->measureLine($node->id, 0.82 * $theme->fontSize);
            $sizes[$node->id] = [
                'w' => max(15.0 * $su, 1.12 * max($labelMetrics->width, $idMetrics->width) + 4.0 * $su),
                'h' => $labelMetrics->height + $idMetrics->height + 3.6 * $su,
            ];
        }

        $columns = min(3, max(1, (int) ceil(sqrt(\count($diagram->nodes)))));
        $rows = (int) ceil(\count($diagram->nodes) / $columns);
        $maxWidth = 0.0;
        $maxHeight = 0.0;
        foreach ($sizes as $size) {
            $maxWidth = max($maxWidth, $size['w']);
            $maxHeight = max($maxHeight, $size['h']);
        }

        $contentWidth = $columns * $maxWidth + max(0, $columns - 1) * $gap;
        $contentHeight = $rows * $maxHeight + max(0, $rows - 1) * $gap;
        $width = max(420.0, $contentWidth + 2.0 * $margin);
        $height = max(260.0, $contentHeight + 2.0 * $margin + $titleHeight);

        $grid = Grid::columns('blocks', $columns)
            ->gap($gap)
            ->align(Alignment::Center, Alignment::Center);
        $layoutNodes = $diagram->nodes;
        if (3 === $columns && \count($layoutNodes) >= 3) {
            // Put the first, usually root, block in the middle of the first
            // row. Its fan-out can then reach both neighbours without passing
            // through the block between them.
            [$layoutNodes[0], $layoutNodes[1]] = [$layoutNodes[1], $layoutNodes[0]];
        }
        foreach ($layoutNodes as $node) {
            $grid = $grid->add(Frame::preferred('block.'.$node->id, $sizes[$node->id]['w'], $sizes[$node->id]['h']));
        }

        $result = (new LayoutSolver(new LayoutContext(snapStep: 0.5)))->solve(
            $grid,
            new Rect($margin, $margin + $titleHeight, $width - 2.0 * $margin, $height - 2.0 * $margin - $titleHeight),
        );

        /** @var array<string, Rect> $frames */
        $frames = [];
        foreach ($diagram->nodes as $node) {
            $frame = $result->frameOf('block.'.$node->id);
            if (null !== $frame) {
                $frames[$node->id] = $frame;
            }
        }

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        if (null !== $diagram->title) {
            $nodes[] = $this->titles->node($diagram->title, $theme, $width / 2.0, $margin);
        }

        $clusterStyle = new ShapeStyle($theme->nodeFillColor, $theme->mutedTextColor, 0.8 * $theme->strokeWidth, LineStyle::Dashed, opacity: 0.7);
        $clusterTextStyle = new TextStyle($theme->fontFamily, 0.82 * $theme->fontSize, FontWeight::Normal, TextAnchor::Start, $theme->mutedTextColor);
        $labelAvoidRects = $frames;
        foreach ($diagram->groups as $group) {
            $cluster = $this->clusterFrame($group->nodeIds, $frames, $su);
            if (null === $cluster) {
                continue;
            }
            $nodes[] = new RectNode($cluster->x, $cluster->y, $cluster->width, $cluster->height, $clusterStyle, 6.0);
            $metrics = $this->measurer->measureLine($group->label, $clusterTextStyle->fontSize);
            $labelX = $cluster->x + $su;
            $labelY = $cluster->y + 0.8 * $su;
            $labelAvoidRects['group.'.$group->id.'.label'] = new Rect($labelX - 0.5 * $su, $labelY - 0.25 * $su, $metrics->width + $su, $metrics->height + 0.5 * $su);
            $nodes[] = new TextNode($labelX, $labelY + $metrics->ascent, $group->label, $clusterTextStyle);
        }

        $labelAvoidIndex = RectIndex::from($labelAvoidRects);
        /** @var list<NodeInterface> $relationshipLabelNodes */
        $relationshipLabelNodes = [];
        foreach ($diagram->relationships as $relationship) {
            $nodes = [...$nodes, ...$this->relationshipNodes($relationship, $frames, $labelAvoidIndex, $theme, false)];
            $relationshipLabelNodes = [
                ...$relationshipLabelNodes,
                ...$this->relationshipNodes($relationship, $frames, $labelAvoidIndex, $theme, true),
            ];
        }

        $blockStyle = new ShapeStyle($theme->nodeFillColor, $theme->mutedTextColor, 0.85 * $theme->strokeWidth);
        $labelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $idStyle = new TextStyle($theme->fontFamily, 0.82 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->mutedTextColor);

        foreach ($diagram->nodes as $node) {
            $frame = $frames[$node->id];
            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $blockStyle, 3.0);

            $labelMetrics = $this->measurer->measureLine($node->label, $labelStyle->fontSize, FontWeight::Bold);
            $idMetrics = $this->measurer->measureLine($node->id, $idStyle->fontSize);
            $labelY = $frame->y + ($frame->height - $labelMetrics->height - $idMetrics->height - 0.4 * $su) / 2.0 + $labelMetrics->ascent + 0.4 * $su;
            $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $labelY, $node->label, $labelStyle);
            $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $labelY + $idMetrics->height + 0.4 * $su, $node->id, $idStyle);
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$relationshipLabelNodes], title: $diagram->title?->text);
    }

    /**
     * @param array<string, Rect> $frames
     *
     * @return list<NodeInterface>
     */
    private function relationshipNodes(BlockRelationship $relationship, array $frames, RectIndex $labelAvoidIndex, Theme $theme, bool $labelsOnly): array
    {
        $connection = $this->connector->connect($frames[$relationship->from], $frames[$relationship->to]);
        $start = $connection->startPoint();
        $end = $connection->endPoint();
        $style = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $nodes = [];

        if ($labelsOnly) {
            if (null === $relationship->label) {
                return [];
            }

            $labelStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);

            return $this->connectionLabels->nodes($relationship->label->text, $connection, $labelAvoidIndex, $theme, $labelStyle);
        }

        if ($connection->isStraight()) {
            $nodes[] = new LineNode($start->x, $start->y, $end->x, $end->y, $style);
        } else {
            $nodes[] = new PathNode(PathData::connection($connection), $style);
        }

        $nodes[] = $this->arrowHeads->arrow($end->x, $end->y, $connection->tipTangent->x, $connection->tipTangent->y, 1.15 * $theme->spacingUnit, 0.45 * $theme->spacingUnit, $theme->nodeStrokeColor);

        return $nodes;
    }

    /**
     * @param non-empty-list<string> $nodeIds
     * @param array<string, Rect>    $frames
     */
    private function clusterFrame(array $nodeIds, array $frames, float $su): ?Rect
    {
        $rects = [];
        foreach ($nodeIds as $nodeId) {
            $frame = $frames[$nodeId] ?? null;
            if (null !== $frame) {
                $rects[] = $frame;
            }
        }

        $bounds = Bounds::fromRects($rects);
        if (null === $bounds) {
            return null;
        }

        $cluster = Bounds::expand($bounds, new Insets(2.5 * $su, 1.5 * $su, 1.5 * $su, 1.5 * $su));

        return new Rect(max(0.0, $cluster->x), max(0.0, $cluster->y), $cluster->width, $cluster->height);
    }
}
