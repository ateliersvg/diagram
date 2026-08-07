<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Venn;

use Atelier\Diagram\Layout\Support\LegendArtist;
use Atelier\Diagram\Layout\Support\TitleArtist;
use Atelier\Diagram\Model\Legend;
use Atelier\Diagram\Model\LegendEntry;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Venn\VennDiagram;
use Atelier\Layout\Alignment;
use Atelier\Layout\Element\TextBlock;
use Atelier\Layout\Geometry\BoxModel;
use Atelier\Layout\Geometry\Circle;
use Atelier\Layout\Geometry\Insets;
use Atelier\Layout\Geometry\Point;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\StrokePlacement;
use Atelier\Layout\LayoutContext;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Schematic Venn layout: fixed geometry, cardinalities ignored.
 *
 * 2 sets: equal circles side by side, center distance 1.2r. 3 sets: the
 * classic triangle arrangement (two circles on top, one below, centers on
 * an equilateral triangle of side 1.2r). Circles are filled with theme
 * accent colors at 0.5 opacity so intersections read visually. Set labels
 * sit outside the circles (above A/B, below C); region labels sit at
 * approximate region centroids. When the diagram opts in, a set -> color
 * legend sits at the bottom left.
 */
final class VennLayoutEngine
{
    /** Circle radius, in theme spacing units (grown to fit wide region labels). */
    private const float RADIUS_UNITS = 10.0;

    /** Distance between circle centers, as a fraction of the radius. */
    private const float CENTER_DISTANCE_FACTOR = 1.2;

    /** Fill opacity of the set circles -- overlaps must stay readable. */
    private const float FILL_OPACITY = 0.5;

    /** Canvas margin, in theme spacing units. */
    private const float MARGIN_UNITS = 3.0;

    /** Gap between a circle edge and its set label, in theme spacing units. */
    private const float LABEL_GAP_UNITS = 1.0;

    /** 2 sets: exclusive-region centroid offset from the circle center, x radius. */
    private const float EXCLUSIVE_OFFSET_TWO = 0.25;

    /** 3 sets: exclusive-region centroid offset from the circle center, x radius. */
    private const float EXCLUSIVE_OFFSET_THREE = 0.5;

    /** 3 sets: pairwise-region centroid offset from the lens midpoint, x radius. */
    private const float PAIR_OFFSET_THREE = 0.3;

    private readonly TitleArtist $titles;
    private readonly LegendArtist $legends;

    public function __construct(
        private readonly TextMeasurerInterface $textMeasurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->titles = new TitleArtist($this->textMeasurer);
        $this->legends = new LegendArtist($this->textMeasurer);
    }

    public function layout(VennDiagram $diagram, Theme $theme): Scene
    {
        if (null !== $diagram->targetWidth && null !== $diagram->targetHeight) {
            return $this->layoutTargeted($diagram, $theme, $diagram->targetWidth, $diagram->targetHeight);
        }

        $unit = $theme->spacingUnit;
        $margin = self::MARGIN_UNITS * $unit;
        $labelGap = self::LABEL_GAP_UNITS * $unit;

        // Radius from theme spacing, grown for label pressure without letting
        // long prose turn the whole Venn into a text billboard.
        $radius = self::RADIUS_UNITS * $unit;
        foreach ($diagram->regionLabels as $label) {
            $width = min(12.0 * $unit, $this->textMeasurer->measureLine($label->text, $theme->fontSize)->width);
            $radius = max($radius, $width / 2.0 + $unit);
        }

        $distance = self::CENTER_DISTANCE_FACTOR * $radius;
        $isTriple = 3 === \count($diagram->sets);
        $rowOffset = $distance * sqrt(3.0) / 2.0;

        // Relative coordinates: x = 0 at the arrangement center, y = 0 at the top of the circles.
        $centers = [
            'A' => [-$distance / 2.0, $radius],
            'B' => [$distance / 2.0, $radius],
        ];
        if ($isTriple) {
            $centers['C'] = [0.0, $radius + $rowOffset];
        }
        $circlesHeight = $isTriple ? 2.0 * $radius + $rowOffset : 2.0 * $radius;
        $regionAnchors = $this->regionAnchors($centers, $radius);

        $setLabelMetrics = $this->textMeasurer->measureLine('M', $theme->fontSize, FontWeight::Bold);
        $regionMetrics = $this->textMeasurer->measureLine('M', $theme->fontSize);
        $setLabelBandHeight = 3.0 * $setLabelMetrics->height;

        // Horizontal extent: circles plus anything text might overhang, all symmetric about x = 0.
        $halfWidth = $distance / 2.0 + $radius;
        foreach ($diagram->sets as $set) {
            $width = $this->textMeasurer->measureLine($set->label, $theme->fontSize, FontWeight::Bold)->width;
            $halfWidth = max($halfWidth, abs($centers[$set->id][0]) + $width / 2.0);
        }
        foreach ($diagram->regionLabels as $region => $label) {
            $width = $this->textMeasurer->measureLine($label->text, $theme->fontSize)->width;
            $halfWidth = max($halfWidth, abs($regionAnchors[$region][0]) + $width / 2.0);
        }
        if (null !== $diagram->title) {
            $halfWidth = max($halfWidth, $this->titles->measure($diagram->title, $theme)->width / 2.0);
        }

        // Optional set -> color legend, rendered at the bottom left.
        $legend = null;
        $legendWidth = 0.0;
        $legendHeight = 0.0;
        $legendTop = 0.0;
        if ($diagram->showLegend) {
            $entries = [];
            foreach ($diagram->sets as $index => $set) {
                $entries[] = new LegendEntry($set->label, $theme->accentColors[$index % \count($theme->accentColors)]);
            }
            $legend = new Legend($entries);
            [$legendWidth, $legendHeight] = $this->legends->measure($legend, $theme);
        }

        $canvasWidth = max(2.0 * ($halfWidth + $margin), $legendWidth + 2.0 * $margin);
        $offsetX = $canvasWidth / 2.0;

        // Vertical flow: margin, title, top label band, circles, bottom label band (3 sets), margin.
        $nodes = [];
        $cursorY = $margin;

        if (null !== $diagram->title) {
            $nodes[] = $this->titles->node($diagram->title, $theme, $offsetX, $cursorY);
            $cursorY += $this->titles->blockHeight($diagram->title, $theme);
        }

        $topLabelTop = $cursorY;
        $cursorY += $setLabelBandHeight + $labelGap;
        $circlesTop = $cursorY;
        $circlesBottom = $circlesTop + $circlesHeight;

        $contentBottom = $circlesBottom;
        $bottomLabelTop = $circlesBottom + $labelGap;
        if ($isTriple) {
            $contentBottom = $circlesBottom + $labelGap + $setLabelBandHeight;
        }
        if (null !== $legend) {
            $legendTop = $contentBottom + 3.0 * $unit;
            $contentBottom = $legendTop + $legendHeight;
        }
        $canvasHeight = $contentBottom + $margin;

        // Circles first, text on top.
        $accentCount = \count($theme->accentColors);
        foreach ($diagram->sets as $index => $set) {
            [$cx, $cy] = $centers[$set->id];
            $nodes[] = new CircleNode(
                $offsetX + $cx,
                $circlesTop + $cy,
                $radius,
                new ShapeStyle(fill: $theme->accentColors[$index % $accentCount], opacity: self::FILL_OPACITY),
            );
        }

        $setLabelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        $labelBoxWidth = max(6.0 * $unit, min(16.0 * $unit, $distance - $unit));
        foreach ($diagram->sets as $set) {
            $top = 'C' === $set->id ? $bottomLabelTop : $topLabelTop;
            $layout = TextBlock::of('venn.set.'.$set->id, $set->label, $theme->fontSize)
                ->weight(FontWeight::Bold)
                ->lineHeight(1.12)
                ->align(Alignment::Center, 'C' === $set->id ? Alignment::Start : Alignment::End)
                ->layout(new LayoutContext(textMeasurer: $this->textMeasurer), new Rect($offsetX + $centers[$set->id][0] - $labelBoxWidth / 2.0, $top, $labelBoxWidth, $setLabelBandHeight));
            foreach ($layout->lines as $line) {
                $nodes[] = new TextNode($line->frame->x + $line->frame->width / 2.0, $line->baseline, $line->text, $setLabelStyle);
            }
        }

        $regionFontSize = 0.86 * $theme->fontSize;
        $regionLabelStyle = new TextStyle($theme->fontFamily, $regionFontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
        $regionBoxWidth = max(5.0 * $unit, min(12.0 * $unit, 0.85 * $radius));
        $regionBoxHeight = 3.4 * $regionMetrics->height;
        foreach (VennDiagram::REGIONS as $region) {
            $label = $diagram->regionLabels[$region] ?? null;
            if (null === $label) {
                continue;
            }
            [$x, $y] = $regionAnchors[$region];
            $layout = TextBlock::of('venn.region.'.$region, $label->text, $regionFontSize)
                ->lineHeight(1.12)
                ->align(Alignment::Center, Alignment::Center)
                ->layout(new LayoutContext(textMeasurer: $this->textMeasurer), new Rect($offsetX + $x - $regionBoxWidth / 2.0, $circlesTop + $y - $regionBoxHeight / 2.0, $regionBoxWidth, $regionBoxHeight));
            foreach ($layout->lines as $line) {
                $nodes[] = new TextNode($line->frame->x + $line->frame->width / 2.0, $line->baseline, $line->text, $regionLabelStyle);
            }
        }

        if (null !== $legend) {
            $nodes = [...$nodes, ...$this->legends->nodes($legend, $theme, $margin, $legendTop)];
        }

        return new Scene($canvasWidth, $canvasHeight, $theme->backgroundColor, $nodes, title: $diagram->title?->text);
    }

    private function layoutTargeted(VennDiagram $diagram, Theme $theme, float $canvasWidth, float $canvasHeight): Scene
    {
        $outer = new Rect(0.0, 0.0, $canvasWidth, $canvasHeight);
        $content = (new BoxModel($outer, $this->percentInsets($canvasWidth, $canvasHeight, $diagram->paddingPercent)))->contentRect();
        $isTriple = 3 === \count($diagram->sets);
        $distanceFactor = self::CENTER_DISTANCE_FACTOR;
        $rowFactor = $isTriple ? 1.0 + $distanceFactor * sqrt(3.0) / 2.0 : 1.0;
        $radius = min(
            $content->width / (2.0 + $distanceFactor),
            $content->height / (2.0 * $rowFactor),
        );
        $radius = max(0.0, $radius);
        $distance = $distanceFactor * $radius;
        $rowOffset = $distance * sqrt(3.0) / 2.0;
        $centerY = $content->y + $content->height / 2.0 - ($isTriple ? $rowOffset / 3.0 : 0.0);

        $centers = [
            'A' => [$content->x + $content->width / 2.0 - $distance / 2.0, $centerY],
            'B' => [$content->x + $content->width / 2.0 + $distance / 2.0, $centerY],
        ];
        if ($isTriple) {
            $centers['C'] = [$content->x + $content->width / 2.0, $centerY + $rowOffset];
        }
        $relativeCenters = [
            'A' => [-$distance / 2.0, $radius],
            'B' => [$distance / 2.0, $radius],
        ];
        if ($isTriple) {
            $relativeCenters['C'] = [0.0, $radius + $rowOffset];
        }
        $regionAnchors = $this->regionAnchors($relativeCenters, $radius);

        /** @var list<\Atelier\Diagram\Scene\NodeInterface> $nodes */
        $nodes = [];
        $accentCount = \count($theme->accentColors);
        foreach ($diagram->sets as $index => $set) {
            [$cx, $cy] = $centers[$set->id];
            $nodes[] = new CircleNode(
                $cx,
                $cy,
                $radius,
                new ShapeStyle(
                    fill: $theme->accentColors[$index % $accentCount],
                    stroke: $theme->nodeStrokeColor,
                    strokeWidth: $diagram->circleStrokeWidth,
                    opacity: self::FILL_OPACITY,
                ),
            );
        }

        $setLabelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, TextAnchor::Middle, $theme->textColor);
        foreach ($diagram->sets as $set) {
            [$cx, $cy] = $centers[$set->id];
            $circle = new Circle(new Point($cx, $cy), $radius);
            $labelBox = $circle->safeSquare(
                $this->percentInsets(2.0 * $radius, 2.0 * $radius, $diagram->innerPaddingPercent),
                $diagram->circleStrokeWidth,
                StrokePlacement::Inside,
            );
            $layout = TextBlock::of('set.'.$set->id, $set->label, $theme->fontSize)
                ->align(Alignment::Center, 'C' === $set->id ? Alignment::End : Alignment::Start)
                ->layout(new LayoutContext(), $labelBox);
            foreach ($layout->lines as $line) {
                $nodes[] = new TextNode($line->frame->x + $line->frame->width / 2.0, $line->baseline, $line->text, $setLabelStyle);
            }
        }

        $regionLabelStyle = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor);
        foreach (VennDiagram::REGIONS as $region) {
            $label = $diagram->regionLabels[$region] ?? null;
            if (null === $label || !isset($regionAnchors[$region])) {
                continue;
            }
            [$x, $y] = $regionAnchors[$region];
            $safe = new Rect(
                $content->x + $content->width / 2.0 + $x - $radius / 2.0,
                $centerY - $radius + $y - $radius / 4.0,
                $radius,
                $radius / 2.0,
            );
            $layout = TextBlock::of('region.'.$region, $label->text, $theme->fontSize)
                ->align(Alignment::Center, Alignment::Center)
                ->breakWords()
                ->layout(new LayoutContext(), $safe);
            foreach ($layout->lines as $line) {
                $nodes[] = new TextNode($line->frame->x + $line->frame->width / 2.0, $line->baseline, $line->text, $regionLabelStyle);
            }
        }

        return new Scene($canvasWidth, $canvasHeight, $theme->backgroundColor, $nodes, title: $diagram->title?->text);
    }

    private function percentInsets(float $width, float $height, float $percent): Insets
    {
        return new Insets(
            $height * $percent / 100.0,
            $width * $percent / 100.0,
            $height * $percent / 100.0,
            $width * $percent / 100.0,
        );
    }

    /**
     * Approximate centroid of every addressable region, in circle-relative
     * coordinates (x = 0 at the arrangement center, y = 0 at the circles' top).
     *
     * @param array<string, array{float, float}> $centers circle centers keyed by set id
     *
     * @return array<string, array{float, float}>
     */
    private function regionAnchors(array $centers, float $radius): array
    {
        if (!isset($centers['C'])) {
            return [
                'A' => [$centers['A'][0] - self::EXCLUSIVE_OFFSET_TWO * $radius, $centers['A'][1]],
                'B' => [$centers['B'][0] + self::EXCLUSIVE_OFFSET_TWO * $radius, $centers['B'][1]],
                'AB' => [0.0, $centers['A'][1]],
            ];
        }

        $centroid = [
            ($centers['A'][0] + $centers['B'][0] + $centers['C'][0]) / 3.0,
            ($centers['A'][1] + $centers['B'][1] + $centers['C'][1]) / 3.0,
        ];

        $anchors = ['ABC' => $centroid];
        foreach (['A', 'B', 'C'] as $id) {
            $anchors[$id] = $this->awayFrom($centers[$id], $centroid, self::EXCLUSIVE_OFFSET_THREE * $radius);
        }
        foreach ([['A', 'B', 'C'], ['A', 'C', 'B'], ['B', 'C', 'A']] as [$first, $second, $opposite]) {
            $midpoint = [
                ($centers[$first][0] + $centers[$second][0]) / 2.0,
                ($centers[$first][1] + $centers[$second][1]) / 2.0,
            ];
            $anchors[$first.$second] = $this->awayFrom($midpoint, $centers[$opposite], self::PAIR_OFFSET_THREE * $radius);
        }

        return $anchors;
    }

    /**
     * Returns $point pushed $offset further away from $reference.
     *
     * @param array{float, float} $point
     * @param array{float, float} $reference
     *
     * @return array{float, float}
     */
    private function awayFrom(array $point, array $reference, float $offset): array
    {
        $dx = $point[0] - $reference[0];
        $dy = $point[1] - $reference[1];
        $length = sqrt($dx * $dx + $dy * $dy);

        return [$point[0] + $offset * $dx / $length, $point[1] + $offset * $dy / $length];
    }
}
