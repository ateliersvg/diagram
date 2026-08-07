<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\C4;

use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\C4\C4Element;
use Atelier\Diagram\C4\C4ElementKind;
use Atelier\Diagram\C4\C4Relationship;
use Atelier\Diagram\Exception\RuntimeException;
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
use Atelier\Layout\Connection\OrthogonalConnector;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

final class C4LayoutEngine
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

    public function layout(C4Diagram $diagram, Theme $theme): Scene
    {
        $su = $theme->spacingUnit;
        $margin = 4.0 * $su;
        $hasRelationshipLabels = [] !== array_filter($diagram->relationships, static fn (C4Relationship $relationship): bool => '' !== $relationship->label->text);
        $boundaryGap = ($hasRelationshipLabels ? 18.0 : 4.0) * $su;
        $nodeGap = ($hasRelationshipLabels ? 10.0 : 2.0) * $su;
        $boundaryPadding = ($hasRelationshipLabels ? 3.0 : 2.0) * $su;
        $boundaryHeaderHeight = 3.5 * $su;
        $nodeHeight = ($hasRelationshipLabels ? 8.5 : 8.0) * $su;
        $titleBlockHeight = $this->titleArtist->blockHeight($diagram->title, $theme);

        $elementsByBoundary = $this->elementsByBoundary($diagram);
        $boundaryIds = $this->layoutBoundaryIds($diagram, $elementsByBoundary);

        $nodeWidths = [];
        foreach ($diagram->elements as $element) {
            $labelWidth = $this->measurer->measureLine($element->label->text, $theme->fontSize, FontWeight::Bold)->width;
            $stereotypeWidth = $this->measurer->measureLine($this->stereotype($element), 0.76 * $theme->fontSize, FontWeight::Bold)->width;
            $nodeWidths[$element->id] = max(19.0 * $su, $labelWidth + 4.0 * $su, $stereotypeWidth + 4.0 * $su);
        }

        /** @var array<string, array{width: float, height: float, cols: int, elements: list<C4Element>, label: string}> $boundaryPlans */
        $boundaryPlans = [];
        $contentWidth = 0.0;
        $contentHeight = 0.0;
        foreach ($boundaryIds as $boundaryId) {
            $elements = $elementsByBoundary[$boundaryId] ?? [];
            $cols = \count($elements) > 2 ? 2 : 1;
            $rows = (int) max(1, ceil(\count($elements) / $cols));
            $maxNodeWidth = 19.0 * $su;
            foreach ($elements as $element) {
                $maxNodeWidth = max($maxNodeWidth, $nodeWidths[$element->id]);
            }

            $boundaryLabel = $this->boundaryLabel($diagram, $boundaryId);
            $boundaryLabelWidth = $this->measurer->measureLine($boundaryLabel, $theme->fontSize, FontWeight::Bold)->width;
            $width = max(
                $boundaryLabelWidth + 2.0 * $boundaryPadding,
                2.0 * $boundaryPadding + $cols * $maxNodeWidth + max(0, $cols - 1) * $nodeGap,
            );
            $height = $boundaryHeaderHeight + 2.0 * $boundaryPadding + $rows * $nodeHeight + max(0, $rows - 1) * $nodeGap;
            $boundaryPlans[$boundaryId] = [
                'width' => $width,
                'height' => $height,
                'cols' => $cols,
                'elements' => $elements,
                'label' => $boundaryLabel,
            ];
            $contentWidth += $width;
            $contentHeight = max($contentHeight, $height);
        }

        $contentWidth += max(0, \count($boundaryIds) - 1) * $boundaryGap;
        $width = max(560.0, $contentWidth + 2.0 * $margin);
        $height = max(340.0, $contentHeight + 2.0 * $margin + $titleBlockHeight);

        /** @var array<string, Rect> $boundaryFrames */
        $boundaryFrames = [];
        /** @var array<string, Rect> $elementFrames */
        $elementFrames = [];
        $left = ($width - $contentWidth) / 2.0;
        $boundaryTop = $margin + $titleBlockHeight;
        foreach ($boundaryIds as $boundaryId) {
            $plan = $boundaryPlans[$boundaryId];
            $frame = new Rect($left, $boundaryTop + ($contentHeight - $plan['height']) / 2.0, $plan['width'], $plan['height']);
            $boundaryFrames[$boundaryId] = $frame;

            $nodeStartX = $frame->x + $boundaryPadding;
            $nodeStartY = $frame->y + $boundaryHeaderHeight + $boundaryPadding;
            $nodeWidth = ($frame->width - 2.0 * $boundaryPadding - max(0, $plan['cols'] - 1) * $nodeGap) / $plan['cols'];
            foreach ($plan['elements'] as $index => $element) {
                $col = $index % $plan['cols'];
                $row = intdiv($index, $plan['cols']);
                $elementFrames[$element->id] = new Rect(
                    $nodeStartX + $col * ($nodeWidth + $nodeGap),
                    $nodeStartY + $row * ($nodeHeight + $nodeGap),
                    $nodeWidth,
                    $nodeHeight,
                );
            }

            $left += $plan['width'] + $boundaryGap;
        }

        /** @var list<NodeInterface> $nodes */
        $nodes = [];
        if (null !== $diagram->title) {
            $nodes[] = $this->titleArtist->node($diagram->title, $theme, $width / 2.0, $margin);
        }

        $labelAvoidRects = $elementFrames;
        foreach ($boundaryIds as $boundaryId) {
            if ('' !== $boundaryId) {
                $nodes = [...$nodes, ...$this->boundaryNodes($boundaryFrames[$boundaryId], $boundaryPlans[$boundaryId]['label'], $theme)];
                $labelAvoidRects['boundary.'.$boundaryId.'.header'] = new Rect($boundaryFrames[$boundaryId]->x, $boundaryFrames[$boundaryId]->y, $boundaryFrames[$boundaryId]->width, $boundaryHeaderHeight);
            }
        }

        $labelAvoidIndex = RectIndex::from($labelAvoidRects);
        /** @var list<NodeInterface> $relationshipLabelNodes */
        $relationshipLabelNodes = [];
        foreach ($diagram->relationships as $relationship) {
            $nodes = [...$nodes, ...$this->relationshipNodes($relationship, $elementFrames, $labelAvoidIndex, $theme, false)];
            $relationshipLabelNodes = [
                ...$relationshipLabelNodes,
                ...$this->relationshipNodes($relationship, $elementFrames, $labelAvoidIndex, $theme, true),
            ];
        }

        foreach ($diagram->elements as $element) {
            $nodes = [...$nodes, ...$this->elementNodes($element, $elementFrames[$element->id], $theme)];
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$nodes, ...$relationshipLabelNodes], title: $diagram->title?->text);
    }

    /**
     * @return array<string, list<C4Element>>
     */
    private function elementsByBoundary(C4Diagram $diagram): array
    {
        $elementsByBoundary = [];
        foreach ($diagram->elements as $element) {
            $elementsByBoundary[$element->boundaryId ?? ''][] = $element;
        }

        return $elementsByBoundary;
    }

    /**
     * @param array<string, list<C4Element>> $elementsByBoundary
     *
     * @return list<string>
     */
    private function layoutBoundaryIds(C4Diagram $diagram, array $elementsByBoundary): array
    {
        $boundaryIds = [];
        foreach ($diagram->boundaries as $boundary) {
            if (isset($elementsByBoundary[$boundary->id]) && [] !== $elementsByBoundary[$boundary->id]) {
                $boundaryIds[] = $boundary->id;
            }
        }
        if (isset($elementsByBoundary['']) && [] !== $elementsByBoundary['']) {
            $boundaryIds[] = '';
        }

        return $boundaryIds;
    }

    private function boundaryLabel(C4Diagram $diagram, string $boundaryId): string
    {
        if ('' === $boundaryId) {
            return 'External';
        }

        foreach ($diagram->boundaries as $boundary) {
            if ($boundary->id === $boundaryId) {
                return $boundary->label->text;
            }
        }

        throw new RuntimeException(\sprintf('C4 boundary "%s" was scheduled for layout but is absent from the diagram boundaries.', $boundaryId));
    }

    /**
     * @return list<NodeInterface>
     */
    private function boundaryNodes(Rect $frame, string $label, Theme $theme): array
    {
        $displayLabel = 'System Boundary: '.$label;
        $style = new ShapeStyle($theme->nodeFillColor, $this->c4Blue($theme), 1.15 * $theme->strokeWidth, LineStyle::Dashed, opacity: 0.92);
        $headerStyle = new ShapeStyle($this->c4Blue($theme), null, 0.0, opacity: 0.16);
        $separatorStyle = ShapeStyle::stroked($theme->nodeStrokeColor, 0.75 * $theme->strokeWidth);
        $textStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Start, $theme->textColor);
        $metrics = $this->measurer->measureLine($displayLabel, $theme->fontSize, FontWeight::Bold);
        $headerHeight = 3.5 * $theme->spacingUnit;

        return [
            new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $style, 2.0),
            new RectNode($frame->x, $frame->y, $frame->width, $headerHeight, $headerStyle, 2.0),
            new LineNode($frame->x, $frame->y + $headerHeight, $frame->right(), $frame->y + $headerHeight, $separatorStyle),
            new TextNode($frame->x + 1.5 * $theme->spacingUnit, $frame->y + ($headerHeight - $metrics->height) / 2.0 + $metrics->ascent, $displayLabel, $textStyle),
        ];
    }

    /**
     * @return list<NodeInterface>
     */
    private function elementNodes(C4Element $element, Rect $frame, Theme $theme): array
    {
        [$fill, $stroke, $opacity, $lineStyle] = $this->colorsFor($element->kind, $theme);
        $boxStyle = new ShapeStyle($fill, $stroke, $theme->strokeWidth, $lineStyle, opacity: $opacity);
        $kindStyle = new TextStyle($theme->fontFamily, 0.76 * $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->mutedTextColor);
        $labelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $detailStyle = new TextStyle($theme->fontFamily, 0.78 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->mutedTextColor);
        $centerX = $frame->x + $frame->width / 2.0;

        $textRows = [
            [$this->stereotype($element), $kindStyle, FontWeight::Bold],
            [$element->label->text, $labelStyle, FontWeight::Bold],
        ];
        if (null !== $element->description) {
            $textRows[] = [$element->description->text, $detailStyle, FontWeight::Normal];
        }

        $rowMetrics = [];
        $textHeight = 0.0;
        foreach ($textRows as [$text, $style, $weight]) {
            $metrics = $this->measurer->measureLine($text, $style->fontSize, $weight);
            $rowMetrics[] = $metrics;
            $textHeight += $metrics->height;
        }
        $rowGap = 0.35 * $theme->spacingUnit;
        $textHeight += max(0, \count($textRows) - 1) * $rowGap;
        $top = $frame->y + ($frame->height - $textHeight) / 2.0;

        $nodes = [new RectNode($frame->x, $frame->y, $frame->width, $frame->height, $boxStyle, $this->cornerRadius($element->kind))];
        foreach ($textRows as $index => [$text, $style]) {
            $metrics = $rowMetrics[$index];
            $nodes[] = new TextNode($centerX, $top + $metrics->ascent, $text, $style);
            $top += $metrics->height + $rowGap;
        }

        return $nodes;
    }

    /**
     * @param array<string, Rect> $frames
     *
     * @return list<NodeInterface>
     */
    private function relationshipNodes(C4Relationship $relationship, array $frames, RectIndex $labelAvoidIndex, Theme $theme, bool $labelsOnly): array
    {
        $connection = $this->connector->connect($frames[$relationship->from], $frames[$relationship->to]);
        $start = $connection->startPoint();
        $end = $connection->endPoint();
        $style = ShapeStyle::stroked($theme->nodeStrokeColor, $theme->strokeWidth);

        if ($labelsOnly) {
            $label = $relationship->label->text;
            if (null !== $relationship->technology) {
                $label .= ' / '.$relationship->technology->text;
            }

            $labelStyle = new TextStyle($theme->fontFamily, 0.72 * $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);

            return $this->connectionLabels->nodes($label, $connection, $labelAvoidIndex, $theme, $labelStyle);
        }

        $nodes = [];
        if ($connection->isStraight()) {
            $nodes[] = new LineNode($start->x, $start->y, $end->x, $end->y, $style);
        } else {
            $nodes[] = new PathNode(PathData::connection($connection), $style);
        }
        $nodes[] = $this->arrowHeads->arrow($end->x, $end->y, $connection->tipTangent->x, $connection->tipTangent->y, 1.15 * $theme->spacingUnit, 0.45 * $theme->spacingUnit, $theme->nodeStrokeColor);

        return $nodes;
    }

    /**
     * @return array{string, string, float, LineStyle}
     */
    private function colorsFor(C4ElementKind $kind, Theme $theme): array
    {
        $blue = $this->c4Blue($theme);
        $database = $this->accentColor($theme, 3);

        return match ($kind) {
            C4ElementKind::Person => [$this->tint($blue, $theme), $blue, 1.0, LineStyle::Solid],
            C4ElementKind::PersonExternal => [$theme->nodeFillColor, $theme->mutedTextColor, 0.98, LineStyle::Dashed],
            C4ElementKind::System => [$this->tint($blue, $theme), $blue, 1.0, LineStyle::Solid],
            C4ElementKind::SystemExternal => [$theme->nodeFillColor, $theme->mutedTextColor, 0.98, LineStyle::Dashed],
            C4ElementKind::Container => [$this->tint($blue, $theme), $blue, 1.0, LineStyle::Solid],
            C4ElementKind::ContainerExternal => [$theme->nodeFillColor, $theme->mutedTextColor, 0.98, LineStyle::Dashed],
            C4ElementKind::ContainerDatabase => [$this->tint($database, $theme), $database, 1.0, LineStyle::Solid],
            C4ElementKind::Component => [$this->tint($blue, $theme), $blue, 1.0, LineStyle::Solid],
            C4ElementKind::ComponentExternal => [$theme->nodeFillColor, $theme->mutedTextColor, 0.98, LineStyle::Dashed],
            C4ElementKind::ComponentDatabase => [$this->tint($database, $theme), $database, 1.0, LineStyle::Solid],
        };
    }

    private function stereotype(C4Element $element): string
    {
        $base = match ($element->kind) {
            C4ElementKind::Person => 'Person',
            C4ElementKind::PersonExternal => 'External Person',
            C4ElementKind::System => 'Software System',
            C4ElementKind::SystemExternal => 'External System',
            C4ElementKind::Container => 'Container',
            C4ElementKind::ContainerExternal => 'External Container',
            C4ElementKind::ContainerDatabase => 'Database',
            C4ElementKind::Component => 'Component',
            C4ElementKind::ComponentExternal => 'External Component',
            C4ElementKind::ComponentDatabase => 'Component DB',
        };

        if (null !== $element->technology && $this->kindCanShowTechnology($element->kind)) {
            $base .= ': '.$element->technology->text;
        }

        return '['.$base.']';
    }

    private function cornerRadius(C4ElementKind $kind): float
    {
        return match ($kind) {
            C4ElementKind::Person, C4ElementKind::PersonExternal => 9.0,
            default => 2.0,
        };
    }

    private function kindCanShowTechnology(C4ElementKind $kind): bool
    {
        return match ($kind) {
            C4ElementKind::Container,
            C4ElementKind::ContainerExternal,
            C4ElementKind::ContainerDatabase,
            C4ElementKind::Component,
            C4ElementKind::ComponentExternal,
            C4ElementKind::ComponentDatabase => true,
            C4ElementKind::Person,
            C4ElementKind::PersonExternal,
            C4ElementKind::System,
            C4ElementKind::SystemExternal => false,
        };
    }

    private function c4Blue(Theme $theme): string
    {
        return $this->accentColor($theme, 0);
    }

    private function accentColor(Theme $theme, int $index): string
    {
        return $theme->accentColors[$index % \count($theme->accentColors)];
    }

    /**
     * A translucent wash of an accent color, so element fills keep their hue
     * on any canvas. Alpha-hex needs a 6-digit hex input; other color
     * notations fall back to the theme node fill.
     */
    private function tint(string $color, Theme $theme): string
    {
        if (1 === preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return $color.'26';
        }

        return $theme->nodeFillColor;
    }
}
