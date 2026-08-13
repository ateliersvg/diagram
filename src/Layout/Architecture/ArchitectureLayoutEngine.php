<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Architecture;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Architecture\ArchitectureNode;
use Atelier\Diagram\Architecture\ArchitectureNodeKind;
use Atelier\Diagram\Architecture\ArchitectureRelationship;
use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Layout\Support\ArrowHeadFactory;
use Atelier\Diagram\Layout\Support\ConnectionLabelArtist;
use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Layout\Support\TitleArtist;
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
use Atelier\Layout\Connection\OrthogonalConnector;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class ArchitectureLayoutEngine
{
    private readonly OrthogonalConnector $connector;

    private readonly ArrowHeadFactory $arrowHeads;

    private readonly TitleArtist $titleArtist;

    private readonly ConnectionLabelArtist $connectionLabels;

    public function __construct(
        private readonly TextMeasurerInterface $measurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->connector = new OrthogonalConnector();
        $this->arrowHeads = new ArrowHeadFactory();
        $this->titleArtist = new TitleArtist($this->measurer);
        $this->connectionLabels = new ConnectionLabelArtist($this->measurer);
    }

    public function layout(ArchitectureDiagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $hasRelationshipLabels = [] !== array_filter($diagram->relationships, static fn (ArchitectureRelationship $relationship): bool => null !== $relationship->label);
        $groupGap = ($hasRelationshipLabels ? 13.0 : 4.0) * $su;
        $nodeGap = ($hasRelationshipLabels ? 5.0 : 2.0) * $su;
        $groupPadding = 2.0 * $su;
        $groupHeaderHeight = 3.25 * $su;
        $nodeHeight = 7.25 * $su;
        $titleBlockHeight = $this->titleArtist->blockHeight($diagram->title, $theme);

        $nodesByGroup = $this->nodesByGroup($diagram);
        $groupIds = $this->layoutGroupIds($diagram, $nodesByGroup);

        $nodeWidths = [];
        foreach ($diagram->nodes as $node) {
            $labelWidth = $this->measurer->measureLine($node->label->text, $theme->fontSize, FontWeight::Bold)->width;
            $kindWidth = $this->measurer->measureLine($this->kindLabel($node->kind), 0.78 * $theme->fontSize)->width;
            $nodeWidths[$node->id] = max(17.0 * $su, $labelWidth + 4.0 * $su, $kindWidth + 4.0 * $su);
        }

        /** @var array<string, array{width: float, height: float, cols: int, nodes: list<ArchitectureNode>, label: string}> $groupPlans */
        $groupPlans = [];
        $contentWidth = 0.0;
        $contentHeight = 0.0;
        foreach ($groupIds as $groupId) {
            $nodes = $nodesByGroup[$groupId] ?? [];
            $cols = \count($nodes) > 2 ? 2 : 1;
            $rows = (int) max(1, ceil(\count($nodes) / $cols));
            $maxNodeWidth = 17.0 * $su;
            foreach ($nodes as $node) {
                $maxNodeWidth = max($maxNodeWidth, $nodeWidths[$node->id]);
            }

            $groupLabel = $this->groupLabel($diagram, $groupId);
            $groupLabelWidth = $this->measurer->measureLine($groupLabel, $theme->fontSize, FontWeight::Bold)->width;
            $width = max(
                $groupLabelWidth + 2.0 * $groupPadding,
                2.0 * $groupPadding + $cols * $maxNodeWidth + max(0, $cols - 1) * $nodeGap,
            );
            $height = $groupHeaderHeight + 2.0 * $groupPadding + $rows * $nodeHeight + max(0, $rows - 1) * $nodeGap;
            $groupPlans[$groupId] = [
                'width' => $width,
                'height' => $height,
                'cols' => $cols,
                'nodes' => $nodes,
                'label' => $groupLabel,
            ];
            $contentWidth += $width;
            $contentHeight = max($contentHeight, $height);
        }

        $contentWidth += max(0, \count($groupIds) - 1) * $groupGap;
        $width = max(480.0, $contentWidth + 2.0 * $margin);
        $height = max(300.0, $contentHeight + 2.0 * $margin + $titleBlockHeight);

        /** @var array<string, Rect> $groupFrames */
        $groupFrames = [];
        /** @var array<string, Rect> $nodeFrames */
        $nodeFrames = [];
        $left = ($width - $contentWidth) / 2.0;
        $groupTop = $margin + $titleBlockHeight;
        foreach ($groupIds as $groupId) {
            $plan = $groupPlans[$groupId];
            $frame = new Rect($left, $groupTop + ($contentHeight - $plan['height']) / 2.0, $plan['width'], $plan['height']);
            $groupFrames[$groupId] = $frame;

            $nodeStartX = $frame->x + $groupPadding;
            $nodeStartY = $frame->y + $groupHeaderHeight + $groupPadding;
            $nodeWidth = ($frame->width - 2.0 * $groupPadding - max(0, $plan['cols'] - 1) * $nodeGap) / $plan['cols'];
            foreach ($plan['nodes'] as $index => $node) {
                $col = $index % $plan['cols'];
                $row = intdiv($index, $plan['cols']);
                $nodeFrames[$node->id] = new Rect(
                    $nodeStartX + $col * ($nodeWidth + $nodeGap),
                    $nodeStartY + $row * ($nodeHeight + $nodeGap),
                    $nodeWidth,
                    $nodeHeight,
                );
            }

            $left += $plan['width'] + $groupGap;
        }

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        if (null !== $diagram->title) {
            $nodes[] = $this->titleArtist->node($diagram->title, $theme, $width / 2.0, $margin);
        }

        $labelAvoidRects = $nodeFrames;
        foreach ($groupIds as $groupId) {
            $nodes = [...$nodes, ...$this->groupNodes($groupFrames[$groupId], $groupPlans[$groupId]['label'], $theme)];
            $labelAvoidRects['group.'.$groupId.'.header'] = new Rect($groupFrames[$groupId]->x, $groupFrames[$groupId]->y, $groupFrames[$groupId]->width, $groupHeaderHeight);
        }

        $labelAvoidIndex = RectIndex::from($labelAvoidRects);
        /** @var list<NodeInterface> $relationshipLabelNodes */
        $relationshipLabelNodes = [];
        foreach ($diagram->relationships as $relationship) {
            $nodes = [...$nodes, ...$this->relationshipNodes($relationship, $nodeFrames, $labelAvoidIndex, $theme, false)];
            $relationshipLabelNodes = [
                ...$relationshipLabelNodes,
                ...$this->relationshipNodes($relationship, $nodeFrames, $labelAvoidIndex, $theme, true),
            ];
        }

        foreach ($diagram->nodes as $node) {
            $nodes = [...$nodes, ...$this->nodeNodes($node, $nodeFrames[$node->id], $theme)];
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$relationshipLabelNodes], title: $diagram->title?->text);
    }

    /**
     * @return array<string, list<ArchitectureNode>>
     */
    private function nodesByGroup(ArchitectureDiagram $diagram): array
    {
        $nodesByGroup = [];
        foreach ($diagram->nodes as $node) {
            $nodesByGroup[$node->groupId ?? ''][] = $node;
        }

        return $nodesByGroup;
    }

    /**
     * @param array<string, list<ArchitectureNode>> $nodesByGroup
     *
     * @return list<string>
     */
    private function layoutGroupIds(ArchitectureDiagram $diagram, array $nodesByGroup): array
    {
        $groupIds = [];
        foreach ($diagram->groups as $group) {
            if (isset($nodesByGroup[$group->id]) && [] !== $nodesByGroup[$group->id]) {
                $groupIds[] = $group->id;
            }
        }
        if (isset($nodesByGroup['']) && [] !== $nodesByGroup['']) {
            $groupIds[] = '';
        }

        return $groupIds;
    }

    private function groupLabel(ArchitectureDiagram $diagram, string $groupId): string
    {
        if ('' === $groupId) {
            return 'Ungrouped';
        }

        foreach ($diagram->groups as $group) {
            if ($group->id === $groupId) {
                return $group->label->text;
            }
        }

        // Unreachable: layoutGroupIds() only emits '' (handled above) or ids that
        // exist in $diagram->groups, so the loop above always returns for non-''.
        throw new RuntimeException(\sprintf('Architecture group "%s" was scheduled for layout but is absent from the diagram groups.', $groupId));
    }

    /**
     * @return list<NodeInterface>
     */
    private function groupNodes(Rect $frame, string $label, Theme $theme): array
    {
        $style = new ShapeStyle($theme->nodeFillColor, $theme->nodeStrokeColor, $theme->strokeWidth, opacity: 0.92);
        $headerStyle = new ShapeStyle($theme->nodeStrokeColor, null, 0.0, opacity: 0.14);
        $railStyle = ShapeStyle::filled($theme->nodeStrokeColor);
        $separatorStyle = ShapeStyle::stroked($theme->nodeStrokeColor, 0.75 * $theme->strokeWidth);
        $textStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->textColor);
        $metrics = $this->measurer->measureLine($label, $theme->fontSize, FontWeight::Bold);
        $headerHeight = 3.25 * $theme->spacingUnit;

        return [
            new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $style, 6.0),
            new RectNode($frame->x, $frame->y, $frame->width, $headerHeight, $headerStyle, 6.0),
            new RectNode($frame->x, $frame->y, 0.55 * $theme->spacingUnit, $headerHeight, $railStyle, 0.0),
            new LineNode($frame->x, $frame->y + $headerHeight, $frame->right(), $frame->y + $headerHeight, $separatorStyle),
            new TextNode($frame->x + 1.5 * $theme->spacingUnit, $frame->y + ($headerHeight - $metrics->height) / 2.0 + $metrics->ascent, $label, $textStyle),
        ];
    }

    /**
     * @return list<NodeInterface>
     */
    private function nodeNodes(ArchitectureNode $node, Rect $frame, Theme $theme): array
    {
        [$fill, $stroke] = $this->colorsFor($node->kind, $theme);
        $boxStyle = new ShapeStyle($fill, $stroke, $theme->strokeWidth);
        $badgeStyle = ShapeStyle::filled($stroke);
        $kindStyle = new TextStyle($theme->fontFamily, 0.66 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->backgroundColor);
        $labelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $kindText = $this->kindLabel($node->kind);
        $kindMetrics = $this->measurer->measureLine($kindText, $kindStyle->fontSize, FontWeight::Bold);
        $labelMetrics = $this->measurer->measureLine($node->label->text, $theme->fontSize, FontWeight::Bold);
        $badgeHeight = 1.65 * $theme->spacingUnit;
        $badgeWidth = min($frame->width - 1.4 * $theme->spacingUnit, $kindMetrics->width + 1.45 * $theme->spacingUnit);
        $badgeX = $frame->x + 0.7 * $theme->spacingUnit;
        $badgeY = $frame->y + 0.7 * $theme->spacingUnit;
        $labelY = $frame->y + $frame->height - 1.25 * $theme->spacingUnit;

        return [
            new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $boxStyle, 5.0),
            new RectNode($badgeX, $badgeY, $badgeWidth, $badgeHeight, $badgeStyle, 2.0),
            new TextNode($badgeX + $badgeWidth / 2.0, $badgeY + ($badgeHeight - $kindMetrics->height) / 2.0 + $kindMetrics->ascent, $kindText, $kindStyle),
            new TextNode($frame->x + $frame->width / 2.0, $labelY - $labelMetrics->height + $labelMetrics->ascent, $node->label->text, $labelStyle),
        ];
    }

    /**
     * @param array<string, Rect> $frames
     *
     * @return list<NodeInterface>
     */
    private function relationshipNodes(ArchitectureRelationship $relationship, array $frames, RectIndex $labelAvoidIndex, Theme $theme, bool $labelsOnly): array
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

            $labelStyle = new TextStyle($theme->fontFamily, 0.72 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);

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
     * @return array{string, string}
     */
    private function colorsFor(ArchitectureNodeKind $kind, Theme $theme): array
    {
        return match ($kind) {
            ArchitectureNodeKind::Person => [$this->tint($this->accentColor($theme, 0), $theme), $this->accentColor($theme, 0)],
            ArchitectureNodeKind::System => [$this->tint($this->accentColor($theme, 2), $theme), $this->accentColor($theme, 2)],
            ArchitectureNodeKind::Container => [$this->tint($this->accentColor($theme, 3), $theme), $this->accentColor($theme, 3)],
            ArchitectureNodeKind::Component => [$theme->nodeFillColor, $theme->nodeStrokeColor],
            ArchitectureNodeKind::Database => [$this->tint($this->accentColor($theme, 4), $theme), $this->accentColor($theme, 4)],
            ArchitectureNodeKind::Queue => [$this->tint($this->accentColor($theme, 5), $theme), $this->accentColor($theme, 5)],
            ArchitectureNodeKind::External => [$theme->nodeFillColor, $theme->mutedTextColor],
        };
    }

    private function accentColor(Theme $theme, int $index): string
    {
        return $theme->accentColors[$index % \count($theme->accentColors)];
    }

    /**
     * A translucent wash of an accent color, so kind fills keep their hue on
     * any canvas. Alpha-hex needs a 6-digit hex input; other color notations
     * fall back to the theme node fill.
     */
    private function tint(string $color, Theme $theme): string
    {
        if (1 === preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return $color.'26';
        }

        return $theme->nodeFillColor;
    }

    private function kindLabel(ArchitectureNodeKind $kind): string
    {
        return match ($kind) {
            ArchitectureNodeKind::Person => 'PERSON',
            ArchitectureNodeKind::System => 'SYSTEM',
            ArchitectureNodeKind::Container => 'CONTAINER',
            ArchitectureNodeKind::Component => 'COMPONENT',
            ArchitectureNodeKind::Database => 'DATABASE',
            ArchitectureNodeKind::Queue => 'QUEUE',
            ArchitectureNodeKind::External => 'EXTERNAL',
        };
    }
}
