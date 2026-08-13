<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Requirement;

use Atelier\Diagram\Layout\Support\ConnectionLabelArtist;
use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementNode;
use Atelier\Diagram\Requirement\RequirementNodeKind;
use Atelier\Diagram\Requirement\RequirementRelationship;
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
use Atelier\Layout\Element\Stack;
use Atelier\Layout\Element\StackBuilder;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\LayoutSolver;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class RequirementLayoutEngine
{
    private readonly OrthogonalConnector $connector;

    private readonly ConnectionLabelArtist $connectionLabels;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->connector = new OrthogonalConnector();
        $this->connectionLabels = new ConnectionLabelArtist($this->measurer);
    }

    public function layout(RequirementDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $groupGap = 12.0 * $su;
        $nodeGap = 3.0 * $su;
        $headerHeight = 5.0 * $su;
        $rowHeight = 2.8 * $su;
        $paddingX = 1.5 * $su;

        $sizes = [];
        foreach ($diagram->nodes as $node) {
            $sizes[$node->id] = $this->nodeSize($node, $theme, $headerHeight, $rowHeight, $paddingX);
        }

        $requirements = $diagram->nodesOfKind(RequirementNodeKind::Requirement);
        $elements = $diagram->nodesOfKind(RequirementNodeKind::Element);
        $requirementsSize = $this->groupSize($requirements, $sizes, $nodeGap);
        $elementsSize = $this->groupSize($elements, $sizes, $nodeGap);

        $width = max(520.0, $requirementsSize['w'] + $elementsSize['w'] + $groupGap + 2.0 * $margin);
        $height = max(260.0, max($requirementsSize['h'], $elementsSize['h']) + 2.0 * $margin + 3.0 * $su);

        $root = Stack::row('requirement.root')
            ->gap($groupGap)
            ->alignItems(Alignment::Start)
            ->add($this->groupStack('requirements', $requirements, $sizes, $nodeGap))
            ->add($this->groupStack('elements', $elements, $sizes, $nodeGap));

        $result = (new LayoutSolver(new LayoutContext(snapStep: 0.5)))->solve($root, new Rect($margin, $margin, $width - 2.0 * $margin, $height - 2.0 * $margin));
        $frames = [];
        foreach ($diagram->nodes as $node) {
            $frame = $result->frameOf('node.'.$node->id);
            if (null !== $frame) {
                $frames[$node->id] = $frame;
            }
        }

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        $labelAvoidIndex = RectIndex::from($frames);
        /** @var list<NodeInterface> $relationshipLabelNodes */
        $relationshipLabelNodes = [];
        foreach ($diagram->relationships as $relationship) {
            $nodes = [...$nodes, ...$this->relationshipNodes($relationship, $frames, $labelAvoidIndex, $theme, false)];
            $relationshipLabelNodes = [
                ...$relationshipLabelNodes,
                ...$this->relationshipNodes($relationship, $frames, $labelAvoidIndex, $theme, true),
            ];
        }

        $groupLabelStyle = new TextStyle($theme->fontFamily, 0.8 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->mutedTextColor);
        $reqGroup = $result->frameOf('group.requirements');
        if (null !== $reqGroup) {
            $nodes[] = new TextNode($reqGroup->x, $reqGroup->y - 1.2 * $su, 'requirements', $groupLabelStyle);
        }
        $elementGroup = $result->frameOf('group.elements');
        if (null !== $elementGroup) {
            $nodes[] = new TextNode($elementGroup->x, $elementGroup->y - 1.2 * $su, 'elements', $groupLabelStyle);
        }

        foreach ($diagram->nodes as $node) {
            $nodes = [...$nodes, ...$this->nodeNodes($node, $frames[$node->id], $theme, $headerHeight, $rowHeight, $paddingX)];
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$relationshipLabelNodes]);
    }

    /**
     * @param list<RequirementNode>                    $nodes
     * @param array<string, array{w: float, h: float}> $sizes
     */
    private function groupStack(string $id, array $nodes, array $sizes, float $gap): StackBuilder
    {
        $stack = Stack::column('group.'.$id)->gap($gap)->alignItems(Alignment::Stretch);

        foreach ($nodes as $node) {
            $stack = $stack->add(Frame::preferred('node.'.$node->id, $sizes[$node->id]['w'], $sizes[$node->id]['h']));
        }

        if ([] === $nodes) {
            $stack = $stack->add(Frame::preferred('empty.'.$id, 1.0, 1.0));
        }

        return $stack;
    }

    /**
     * @return array{w: float, h: float}
     */
    private function nodeSize(RequirementNode $node, Theme $theme, float $headerHeight, float $rowHeight, float $paddingX): array
    {
        $width = min(
            38.0 * $theme->spacingUnit,
            max(
                20.0 * $theme->spacingUnit,
                $this->measurer->measureLine($node->id, $theme->fontSize, FontWeight::Bold)->width + 2.0 * $paddingX,
                $this->fieldPreferredWidth($node, $theme, $paddingX),
            ),
        );

        $height = $headerHeight;
        foreach ($this->fieldRows($node, $theme, $width, $rowHeight, $paddingX) as $row) {
            $height += $row['height'];
        }

        return [
            'w' => $width,
            'h' => $height,
        ];
    }

    /**
     * @param list<RequirementNode>                    $nodes
     * @param array<string, array{w: float, h: float}> $sizes
     *
     * @return array{w: float, h: float}
     */
    private function groupSize(array $nodes, array $sizes, float $gap): array
    {
        $width = 1.0;
        $height = 1.0;
        foreach ($nodes as $index => $node) {
            $width = max($width, $sizes[$node->id]['w']);
            $height += $sizes[$node->id]['h'];
            if ($index > 0) {
                $height += $gap;
            }
        }

        return ['w' => $width, 'h' => $height];
    }

    /**
     * @return list<NodeInterface>
     */
    private function nodeNodes(RequirementNode $node, Rect $frame, Theme $theme, float $headerHeight, float $rowHeight, float $paddingX): array
    {
        $accent = RequirementNodeKind::Requirement === $node->kind ? $theme->accentColors[4 % \count($theme->accentColors)] : $theme->accentColors[2 % \count($theme->accentColors)];
        $boxStyle = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth);
        $headerStyle = ShapeStyle::filled($accent);
        $separatorStyle = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $titleStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->backgroundColor);
        $kindStyle = new TextStyle($theme->fontFamily, 0.72 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->backgroundColor);
        $fieldStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Normal, TextAnchor::Start, $theme->textColor);
        $fieldNameStyle = new TextStyle($theme->fontFamily, 0.85 * $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->textColor);

        $nodes = [
            new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $boxStyle, 5.0),
            new RectNode($frame->x, $frame->y, $frame->width, $headerHeight, $headerStyle, 5.0),
            new LineNode($frame->x, $frame->y + $headerHeight, $frame->right(), $frame->y + $headerHeight, $separatorStyle),
        ];

        $titleMetrics = $this->measurer->measureLine($node->id, $theme->fontSize, FontWeight::Bold);
        $kindMetrics = $this->measurer->measureLine($node->kind->value, $kindStyle->fontSize);
        $titleBaseline = $frame->y + 0.8 * $theme->spacingUnit + $titleMetrics->ascent;
        $kindBaseline = $titleBaseline + $titleMetrics->height + 0.1 * $theme->spacingUnit;
        $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $titleBaseline, $node->id, $titleStyle);
        $nodes[] = new TextNode($frame->x + $frame->width / 2.0, $kindBaseline + $kindMetrics->ascent - $kindMetrics->height, $node->kind->value, $kindStyle);

        $rowTop = $frame->y + $headerHeight;
        foreach ($this->fieldRows($node, $theme, $frame->width, $rowHeight, $paddingX) as $row) {
            $name = $row['name'];
            $value = $row['value'];
            $metrics = $this->measurer->measureLine($name, $fieldNameStyle->fontSize, FontWeight::Bold);
            $baseline = $rowTop + 0.55 * $theme->spacingUnit + $metrics->ascent;
            $nodes[] = new TextNode($frame->x + $paddingX, $baseline, $name.':', $fieldNameStyle);
            $valueX = $frame->x + $paddingX + $row['nameWidth'] + 0.75 * $theme->spacingUnit;
            $layout = TextBlock::of('requirement.'.$node->id.'.'.$name, $value, $fieldStyle->fontSize)
                ->lineHeight(1.18)
                ->breakWords()
                ->layout(
                    new LayoutContext(textMeasurer: $this->measurer),
                    new Rect($valueX, $rowTop + 0.55 * $theme->spacingUnit, $row['valueWidth'], $row['valueHeight']),
                );
            foreach ($layout->lines as $line) {
                $nodes[] = new TextNode($line->frame->x, $line->baseline, $line->text, $fieldStyle);
            }
            $rowTop += $row['height'];
        }

        return $nodes;
    }

    private function fieldPreferredWidth(RequirementNode $node, Theme $theme, float $paddingX): float
    {
        $width = 0.0;
        $nameColumnWidth = $this->fieldNameColumnWidth($node, $theme);
        foreach ($node->fields as $name => $value) {
            $valueWidth = min(20.0 * $theme->spacingUnit, 1.15 * $this->measurer->measureLine($value, 0.85 * $theme->fontSize)->width);
            $width = max($width, $nameColumnWidth + 1.5 * $theme->spacingUnit + $valueWidth + 2.0 * $paddingX);
        }

        return $width;
    }

    /**
     * @return list<array{name: string, value: string, nameWidth: float, valueWidth: float, valueHeight: float, height: float}>
     */
    private function fieldRows(RequirementNode $node, Theme $theme, float $width, float $rowHeight, float $paddingX): array
    {
        if ([] === $node->fields) {
            return [[
                'name' => 'type',
                'value' => $node->kind->value,
                'nameWidth' => $this->measurer->measureLine('type', 0.85 * $theme->fontSize, FontWeight::Bold)->width,
                'valueWidth' => max(1.0, $width - 2.0 * $paddingX),
                'valueHeight' => $rowHeight,
                'height' => $rowHeight,
            ]];
        }

        $rows = [];
        $nameColumnWidth = $this->fieldNameColumnWidth($node, $theme);
        foreach ($node->fields as $name => $value) {
            $nameWidth = $nameColumnWidth;
            $valueWidth = max(4.0 * $theme->spacingUnit, $width - 2.0 * $paddingX - $nameWidth - 1.5 * $theme->spacingUnit);
            $block = $this->measurer->wrap($value, $valueWidth, 0.85 * $theme->fontSize, 1.18, true);
            $valueHeight = max($rowHeight - 1.1 * $theme->spacingUnit, $block->height);
            $rows[] = [
                'name' => $name,
                'value' => $value,
                'nameWidth' => $nameWidth,
                'valueWidth' => $valueWidth,
                'valueHeight' => $valueHeight,
                'height' => max($rowHeight, $valueHeight + 1.1 * $theme->spacingUnit),
            ];
        }

        return $rows;
    }

    private function fieldNameColumnWidth(RequirementNode $node, Theme $theme): float
    {
        $width = 0.0;
        foreach (array_keys($node->fields) as $name) {
            $width = max(
                $width,
                1.5 * $this->measurer->measureLine($name.':', 0.85 * $theme->fontSize, FontWeight::Bold)->width,
            );
        }

        return $width;
    }

    /**
     * @param array<string, Rect> $frames
     *
     * @return list<NodeInterface>
     */
    private function relationshipNodes(RequirementRelationship $relationship, array $frames, RectIndex $labelAvoidIndex, Theme $theme, bool $labelsOnly): array
    {
        $from = $frames[$relationship->from];
        $to = $frames[$relationship->to];
        $connection = $this->connector->connect($from, $to);
        $start = $connection->startPoint();
        $end = $connection->endPoint();
        $style = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);
        $nodes = [];

        if ($labelsOnly) {
            $labelStyle = new TextStyle($theme->fontFamily, 0.8 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->nodeStrokeColor);

            return $this->connectionLabels->nodes($relationship->kind->value, $connection, $labelAvoidIndex, $theme, $labelStyle);
        }

        if ($connection->isStraight()) {
            $nodes[] = new LineNode($start->x, $start->y, $end->x, $end->y, $style);
        } else {
            $nodes[] = new PathNode(PathData::connection($connection), $style);
        }

        return $nodes;
    }
}
