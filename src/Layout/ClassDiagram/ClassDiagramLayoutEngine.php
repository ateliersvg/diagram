<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\ClassDiagram;

use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassRelation;
use Atelier\Diagram\Layout\Support\ArrowHeadFactory;
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
use Atelier\Layout\Connection\OrthogonalConnector;
use Atelier\Layout\Element\Frame;
use Atelier\Layout\Element\Grid;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextLayout;
use Atelier\Layout\Text\TextMeasurerInterface;

final class ClassDiagramLayoutEngine
{
    private readonly OrthogonalConnector $connector;

    private readonly ArrowHeadFactory $arrowHeads;

    private readonly ConnectionLabelArtist $connectionLabels;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->connector = new OrthogonalConnector();
        $this->arrowHeads = new ArrowHeadFactory();
        $this->connectionLabels = new ConnectionLabelArtist($this->measurer);
    }

    public function layout(ClassDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $hasRelationLabels = [] !== array_filter($diagram->relations, static fn (ClassRelation $relation): bool => null !== $relation->label);
        $gap = ($hasRelationLabels ? 14.0 : 5.0) * $su;
        $paddingX = 1.5 * $su;
        $context = new LayoutContext(textMeasurer: $this->measurer);

        $sizes = [];
        $headerHeights = [];
        $memberHeights = [];
        foreach ($diagram->classes as $class) {
            $naturalWidth = $this->measurer->measureLine($class->id, $theme->fontSize, FontWeight::Bold)->width + 2.0 * $paddingX;
            foreach ($class->members as $member) {
                $naturalWidth = max($naturalWidth, $this->measurer->measureLine($member->text, 0.9 * $theme->fontSize)->width + 2.0 * $paddingX);
            }
            $width = min(max(16.0 * $su, $naturalWidth), 30.0 * $su);
            $titleLayout = $this->textLayout('class.'.$class->id.'.title', $class->id, $width - 2.0 * $paddingX, $theme->fontSize, FontWeight::Bold, 1.12, $context);
            $headerHeights[$class->id] = max(3.2 * $su, $titleLayout->contentHeight + 1.35 * $su);

            $height = $headerHeights[$class->id];
            $memberHeights[$class->id] = [];
            foreach ($class->members as $index => $member) {
                $memberLayout = $this->textLayout('class.'.$class->id.'.member.'.$index, $member->text, $width - 2.0 * $paddingX, 0.9 * $theme->fontSize, FontWeight::Normal, 1.16, $context);
                $memberHeight = max(3.2 * $su, $memberLayout->contentHeight + 2.0 * $su);
                $memberHeights[$class->id][$index] = $memberHeight;
                $height += $memberHeight;
            }
            if ([] === $class->members) {
                $height += 2.7 * $su;
            }
            $sizes[$class->id] = [
                'w' => $width,
                'h' => $height,
            ];
        }

        $contentWidth = max(0, \count($diagram->classes) - 1) * $gap;
        $contentHeight = 0.0;
        foreach ($sizes as $size) {
            $contentWidth += $size['w'];
            $contentHeight = max($contentHeight, $size['h']);
        }
        $width = max(360.0, $contentWidth + 2.0 * $margin);
        $height = max(220.0, $contentHeight + 2.0 * $margin);

        $grid = Grid::columns('classes', \count($diagram->classes))
            ->gap($gap)
            ->align(Alignment::Center, Alignment::Center);
        foreach ($diagram->classes as $class) {
            $grid = $grid->add(Frame::preferred('class.'.$class->id, $sizes[$class->id]['w'], $sizes[$class->id]['h']));
        }

        $result = (new LayoutSolver(new LayoutContext(snapStep: 0.5)))->solve($grid, new Rect($margin, $margin, $width - 2.0 * $margin, $height - 2.0 * $margin));

        $frames = [];
        foreach ($diagram->classes as $class) {
            $frame = $result->frameOf('class.'.$class->id);
            if (null !== $frame) {
                $frames[$class->id] = $frame;
            }
        }

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        $labelAvoidIndex = RectIndex::from($frames);
        /** @var list<NodeInterface> $relationLabelNodes */
        $relationLabelNodes = [];
        foreach ($diagram->relations as $relation) {
            $nodes = [...$nodes, ...$this->relationNodes($relation, $frames, $labelAvoidIndex, $theme, false)];
            $relationLabelNodes = [
                ...$relationLabelNodes,
                ...$this->relationNodes($relation, $frames, $labelAvoidIndex, $theme, true),
            ];
        }

        $boxStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $separatorStyle = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $titleStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $memberStyle = new TextStyle($theme->fontFamily, 0.9 * $theme->fontSize, FontWeight::Normal, TextAnchor::Start, $theme->textColor);
        foreach ($diagram->classes as $class) {
            $frame = $frames[$class->id];
            $headerHeight = $headerHeights[$class->id];
            $nodes[] = new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $boxStyle, 4.0);
            $nodes[] = new LineNode($frame->x, $frame->y + $headerHeight, $frame->right(), $frame->y + $headerHeight, $separatorStyle);
            $titleLayout = $this->textLayout('class.'.$class->id.'.title', $class->id, $frame->width - 2.0 * $paddingX, $theme->fontSize, FontWeight::Bold, 1.12, $context, $frame->x + $paddingX, $frame->y + ($headerHeight - $this->textLayout('class.'.$class->id.'.title.measure', $class->id, $frame->width - 2.0 * $paddingX, $theme->fontSize, FontWeight::Bold, 1.12, $context)->contentHeight) / 2.0);
            foreach ($titleLayout->lines as $line) {
                $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $line->baseline, $line->text, $titleStyle);
            }

            $rowTop = $frame->y + $headerHeight;
            foreach ($class->members as $index => $member) {
                $rowHeight = $memberHeights[$class->id][$index];
                $measureLayout = $this->textLayout('class.'.$class->id.'.member.'.$index.'.measure', $member->text, $frame->width - 2.0 * $paddingX, $memberStyle->fontSize, FontWeight::Normal, 1.16, $context);
                $memberLayout = $this->textLayout('class.'.$class->id.'.member.'.$index, $member->text, $frame->width - 2.0 * $paddingX, $memberStyle->fontSize, FontWeight::Normal, 1.16, $context, $frame->x + $paddingX, $rowTop + ($rowHeight - $measureLayout->contentHeight) / 2.0);
                foreach ($memberLayout->lines as $line) {
                    $nodes[] = new TextNode($line->frame->x, $line->baseline, $line->text, $memberStyle);
                }
                $rowTop += $rowHeight;
            }
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$relationLabelNodes]);
    }

    /**
     * @param array<string, Rect> $frames
     *
     * @return list<NodeInterface>
     */
    private function relationNodes(ClassRelation $relation, array $frames, RectIndex $labelAvoidIndex, Theme $theme, bool $labelsOnly): array
    {
        $from = $frames[$relation->from];
        $to = $frames[$relation->to];
        $connection = $this->connector->connect($from, $to);
        $start = $connection->startPoint();
        $end = $connection->endPoint();
        $style = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $nodes = [];

        if ($labelsOnly) {
            if (null === $relation->label) {
                return [];
            }

            $labelStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);

            return $this->connectionLabels->nodes($relation->label->text, $connection, $labelAvoidIndex, $theme, $labelStyle);
        }

        if ($connection->isStraight()) {
            $nodes[] = new LineNode($start->x, $start->y, $end->x, $end->y, $style);
        } else {
            $nodes[] = new PathNode(PathData::connection($connection), $style);
        }
        $nodes[] = $this->arrowHeads->arrow($end->x, $end->y, $connection->tipTangent->x, $connection->tipTangent->y, 1.15 * $theme->spacingUnit, 0.45 * $theme->spacingUnit, $theme->nodeStrokeColor);

        return $nodes;
    }

    private function textLayout(string $id, string $text, float $width, float $fontSize, FontWeight $weight, float $lineHeight, LayoutContext $context, float $x = 0.0, float $y = 0.0): TextLayout
    {
        return TextBlock::of($id, $text, $fontSize)
            ->weight($weight)
            ->lineHeight($lineHeight)
            ->breakWords()
            ->align(Alignment::Center, Alignment::Start)
            ->layout($context, new Rect($x, $y, $width, 1_000_000.0));
    }
}
