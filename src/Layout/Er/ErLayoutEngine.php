<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Er;

use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErRelationship;
use Atelier\Diagram\Layout\Support\ConnectionLabelArtist;
use Atelier\Diagram\Layout\Support\PathData;
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
use Atelier\Layout\Connection\ConnectionLabelPlacement;
use Atelier\Layout\Connection\OrthogonalConnection;
use Atelier\Layout\Connection\OrthogonalConnector;
use Atelier\Layout\Element\Frame;
use Atelier\Layout\Element\Grid;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class ErLayoutEngine
{
    private readonly OrthogonalConnector $connector;

    private readonly ConnectionLabelArtist $connectionLabels;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->connector = new OrthogonalConnector();
        $this->connectionLabels = new ConnectionLabelArtist($this->measurer);
    }

    public function layout(ErDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $hasRelationshipLabels = [] !== array_filter($diagram->relationships, static fn (ErRelationship $relationship): bool => null !== $relationship->label);
        $gap = ($hasRelationshipLabels ? 18.0 : 6.0) * $su;
        $headerHeight = 3.25 * $su;
        $rowHeight = 2.7 * $su;
        $paddingX = 1.5 * $su;
        $columnGap = 2.5 * $su;

        $sizes = [];
        foreach ($diagram->entities as $entity) {
            $width = max(15.0 * $su, $this->measurer->measureLine($entity->id, $theme->fontSize, FontWeight::Bold)->width + 2.0 * $paddingX);
            foreach ($entity->attributes as $attribute) {
                // Name sits left, type right; reserve a real gap between them
                // so long names like "trackingNumber" never collide the type.
                $nameWidth = $this->measurer->measureLine($attribute->name, 0.9 * $theme->fontSize)->width;
                $typeWidth = $this->measurer->measureLine($attribute->type, 0.82 * $theme->fontSize)->width;
                // Browser font metrics are wider than the deliberately cheap
                // character-width estimator. Keep a small safety factor so
                // the right-aligned type cannot run back into the name.
                $width = max($width, 1.4 * ($nameWidth + $typeWidth) + $columnGap + 2.0 * $paddingX);
            }
            $sizes[$entity->id] = [
                'w' => $width,
                'h' => $headerHeight + max(1, \count($entity->attributes)) * $rowHeight,
            ];
        }

        // Wrap entities into a near-square grid instead of one long row, so a
        // schema with several entities stays compact and leaves vertical room
        // for the relationship edges.
        $count = \count($diagram->entities);
        $columns = max(1, (int) \ceil(\sqrt($count)));
        $rows = (int) \ceil($count / $columns);
        $cellWidth = 0.0;
        $cellHeight = 0.0;
        foreach ($sizes as $size) {
            $cellWidth = max($cellWidth, $size['w']);
            $cellHeight = max($cellHeight, $size['h']);
        }
        $gridWidth = $columns * $cellWidth + max(0, $columns - 1) * $gap;
        $gridHeight = $rows * $cellHeight + max(0, $rows - 1) * $gap;
        $width = max(420.0, $gridWidth + 2.0 * $margin);
        $height = max(240.0, $gridHeight + 2.0 * $margin + 3.0 * $su);

        $grid = Grid::columns('entities', $columns)
            ->gap($gap)
            ->align(Alignment::Center, Alignment::Center);
        foreach ($diagram->entities as $entity) {
            $grid = $grid->add(Frame::preferred('entity.'.$entity->id, $sizes[$entity->id]['w'], $sizes[$entity->id]['h']));
        }

        $result = (new LayoutSolver(new LayoutContext(snapStep: 0.5)))->solve($grid, new Rect($margin, $margin, $width - 2.0 * $margin, $height - 2.0 * $margin));

        $frames = [];
        foreach ($diagram->entities as $entity) {
            $frame = $result->frameOf('entity.'.$entity->id);
            if (null !== $frame) {
                $frames[$entity->id] = $frame;
            }
        }

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        $labelAvoidRects = $frames;
        $labelAvoidIndex = RectIndex::from($labelAvoidRects);
        /** @var list<NodeInterface> $relationshipLabelNodes */
        $relationshipLabelNodes = [];
        $relationshipIndex = 0;
        foreach ($diagram->relationships as $relationship) {
            $nodes = [...$nodes, ...$this->relationshipNodes($relationship, $frames, $labelAvoidIndex, $theme, false)];
            $labels = $this->relationshipNodes($relationship, $frames, $labelAvoidIndex, $theme, true, $relationshipIndex);
            $relationshipLabelNodes = [...$relationshipLabelNodes, ...$labels];
            foreach ($labels as $index => $labelNode) {
                if ($labelNode instanceof RectNode) {
                    $labelAvoidRects['relationship.label.'.\count($relationshipLabelNodes).'.'.$index] = new Rect(
                        $labelNode->x,
                        $labelNode->y,
                        $labelNode->width,
                        $labelNode->height,
                    );
                }
            }
            $labelAvoidIndex = RectIndex::from($labelAvoidRects);
            ++$relationshipIndex;
        }

        $boxStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $headerStyle = ShapeStyle::filled($theme->accentColors[0]);
        $separatorStyle = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $titleStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->backgroundColor);
        $attributeStyle = new TextStyle($theme->fontFamily, 0.9 * $theme->fontSize, FontWeight::Normal, TextAnchor::Start, $theme->textColor);
        $typeStyle = new TextStyle($theme->fontFamily, 0.82 * $theme->fontSize, FontWeight::Normal, TextAnchor::End, $theme->mutedTextColor);

        foreach ($diagram->entities as $entity) {
            $frame = $frames[$entity->id];
            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $boxStyle, 5.0);
            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $headerHeight, $headerStyle, 5.0);
            $nodes[] = new LineNode($frame->x, $frame->y + $headerHeight, $frame->right(), $frame->y + $headerHeight, $separatorStyle);

            $titleMetrics = $this->measurer->measureLine($entity->id, $theme->fontSize, FontWeight::Bold);
            $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $frame->y + ($headerHeight - $titleMetrics->height) / 2.0 + $titleMetrics->ascent, $entity->id, $titleStyle);

            foreach ($entity->attributes as $index => $attribute) {
                $rowTop = $frame->y + $headerHeight + $index * $rowHeight;
                $metrics = $this->measurer->measureLine($attribute->name, $attributeStyle->fontSize);
                $typeMetrics = $this->measurer->measureLine($attribute->type, $typeStyle->fontSize);
                $baseline = $rowTop + ($rowHeight - $metrics->height) / 2.0 + $metrics->ascent;
                $nodes[] = new TextNode($frame->x + $paddingX, $baseline, $attribute->name, $attributeStyle);
                $nodes[] = new TextNode($frame->right() - $paddingX, $baseline - ($typeMetrics->ascent - $metrics->ascent), $attribute->type, $typeStyle);
            }
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$relationshipLabelNodes]);
    }

    /**
     * @param array<string, Rect> $frames
     *
     * @return list<NodeInterface>
     */
    private function relationshipNodes(ErRelationship $relationship, array $frames, RectIndex $labelAvoidIndex, Theme $theme, bool $labelsOnly, int $relationshipIndex = 0): array
    {
        $from = $frames[$relationship->from];
        $to = $frames[$relationship->to];
        $connection = $this->connector->connect($from, $to);
        $start = $connection->startPoint();
        $end = $connection->endPoint();
        $style = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $nodes = [];

        if ($labelsOnly) {
            $cardinalityStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->nodeStrokeColor);
            $fromCardinality = $this->cardinalityPosition($connection, true, $theme);
            $toCardinality = $this->cardinalityPosition($connection, false, $theme);
            $nodes = [
                ...$nodes,
                ...$this->cardinalityNodes($relationship->fromCardinality->value, $fromCardinality['x'], $fromCardinality['baseline'], $theme, $cardinalityStyle),
                ...$this->cardinalityNodes($relationship->toCardinality->value, $toCardinality['x'], $toCardinality['baseline'], $theme, $cardinalityStyle),
            ];

            if (null !== $relationship->label) {
                $labelStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
                $nodes = [
                    ...$nodes,
                    ...$this->connectionLabels->nodes(
                        $relationship->label->text,
                        $connection,
                        $labelAvoidIndex,
                        $theme,
                        $labelStyle,
                        0 === $relationshipIndex % 2 ? ConnectionLabelPlacement::Above : ConnectionLabelPlacement::Below,
                    ),
                ];
            }

            return $nodes;
        }

        if ($connection->isStraight()) {
            $nodes[] = new LineNode($start->x, $start->y, $end->x, $end->y, $style);
        } else {
            $nodes[] = new PathNode(PathData::connection($connection), $style);
        }

        return $nodes;
    }

    /**
     * @return list<NodeInterface>
     */
    private function cardinalityNodes(string $text, float $x, float $baseline, Theme $theme, TextStyle $style): array
    {
        $su = $theme->spacingUnit;
        $metrics = $this->measurer->measureLine($text, $style->fontSize, $style->fontWeight);

        return [
            new RectNode(
                $x - $metrics->width / 2.0 - 0.45 * $su,
                $baseline - $metrics->ascent - 0.2 * $su,
                $metrics->width + 0.9 * $su,
                $metrics->height + 0.4 * $su,
                new ShapeStyle(fill: $theme->backgroundColor, opacity: 0.96),
                0.3 * $su,
            ),
            new TextNode($x, $baseline, $text, $style),
        ];
    }

    /**
     * @return array{x: float, baseline: float}
     */
    private function cardinalityPosition(OrthogonalConnection $connection, bool $start, Theme $theme): array
    {
        $point = $start ? $connection->startPoint() : $connection->endPoint();
        $next = $start ? $connection->points[1] : $connection->points[\count($connection->points) - 2];
        $dx = $start ? $next->x - $point->x : $point->x - $next->x;
        $dy = $start ? $next->y - $point->y : $point->y - $next->y;

        if (abs($dy) > abs($dx)) {
            $direction = $dy >= 0.0 ? 1.0 : -1.0;

            return [
                'x' => $point->x + 1.7 * $theme->spacingUnit,
                'baseline' => $point->y + ($start ? $direction * 1.8 : -$direction * 0.8) * $theme->spacingUnit,
            ];
        }

        $direction = $dx >= 0.0 ? 1.0 : -1.0;

        return [
            'x' => $point->x + ($start ? $direction * 2.4 : -$direction * 2.4) * $theme->spacingUnit,
            'baseline' => $point->y - 0.65 * $theme->spacingUnit,
        ];
    }
}
