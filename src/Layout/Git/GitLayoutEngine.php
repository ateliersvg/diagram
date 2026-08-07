<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Git;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Layout\Support\LegendArtist;
use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Diagram\Layout\Support\TitleArtist;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Legend;
use Atelier\Diagram\Model\LegendEntry;
use Atelier\Diagram\Scene\CircleNode;
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
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;

/**
 * Lays out a GitGraph as branch lanes.
 *
 * One lane per branch in creation order (rows in LeftToRight, columns in
 * TopToBottom); commits sit on the axis in operation order at uniform
 * spacing. Edges connect commits to their parents: a straight segment
 * within a lane, a rounded 90-degree connection across lanes (branch creation
 * and merge). Regular commits are filled dots in their branch accent color,
 * merge commits hollow dots; commit ids are muted captions next to the
 * dots, tags small labeled badges. Unless the graph opts out, a branch ->
 * color legend sits at the bottom left.
 */
final class GitLayoutEngine
{
    /** Lane strands and merge-dot outlines, slightly heavier than the base stroke. */
    private const float EDGE_WIDTH_FACTOR = 4.0 / 3.0;
    private const float SMALL_FONT_FACTOR = 0.75;
    private const float TURN_RADIUS_FACTOR = 1.25;

    private readonly TitleArtist $titles;
    private readonly LegendArtist $legends;

    public function __construct(
        private readonly TextMeasurerInterface $textMeasurer = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92),
    ) {
        $this->titles = new TitleArtist($this->textMeasurer);
        $this->legends = new LegendArtist($this->textMeasurer);
    }

    public function layout(GitGraph $graph, Theme $theme): Scene
    {
        $ltr = Direction::LeftToRight === $graph->direction;
        $unit = $theme->spacingUnit;
        $margin = 3.0 * $unit;
        $commitSpacing = 7.0 * $unit;
        $laneSpacing = 7.0 * $unit;
        $dotRadius = 0.875 * $unit;
        $edgeWidth = self::EDGE_WIDTH_FACTOR * $theme->strokeWidth;
        $smallFontSize = self::SMALL_FONT_FACTOR * $theme->fontSize;
        $badgePadX = 0.75 * $unit;
        $badgePadY = 0.25 * $unit;

        // Lane assignment: branch creation order.
        $laneOf = [];
        foreach ($graph->branches as $index => $branch) {
            $laneOf[$branch->name] = $index;
        }

        // Measured extents of the fixed decorations.
        $labelMaxWidth = 0.0;
        foreach ($graph->branches as $branch) {
            $labelMaxWidth = max($labelMaxWidth, $this->textMeasurer->measureLine($branch->name, $theme->fontSize, FontWeight::Bold)->width);
        }
        $labelLineHeight = 1.4 * $theme->fontSize;
        if (!$ltr) {
            $laneSpacing = max($laneSpacing, $labelMaxWidth + 2.0 * $unit);
        }

        $hasTags = false;
        $badgeMaxWidth = 0.0;
        foreach ($graph->commits as $commit) {
            if (null !== $commit->tag) {
                $hasTags = true;
                $badgeMaxWidth = max($badgeMaxWidth, $this->textMeasurer->measureLine($commit->tag, $smallFontSize)->width + 2.0 * $badgePadX);
            }
        }
        $badgeHeight = 1.4 * $smallFontSize + 2.0 * $badgePadY;

        $titleBlock = $this->titles->blockHeight($graph->title, $theme);

        // Origins. The "axis" runs in the flow direction (x in LR, y in TB);
        // the "lane" coordinate is perpendicular to it.
        if ($ltr) {
            $axisOrigin = $margin + $labelMaxWidth + 3.0 * $unit;
            $laneTopRoom = max($dotRadius, $labelLineHeight / 2.0);
            if ($hasTags) {
                $laneTopRoom = max($laneTopRoom, $dotRadius + 0.5 * $unit + $badgeHeight);
            }
            $laneOrigin = $margin + $titleBlock + $laneTopRoom;
        } else {
            $axisOrigin = $margin + $titleBlock + $labelLineHeight + 2.0 * $unit;
            $laneLeftRoom = max($dotRadius, $labelMaxWidth / 2.0);
            if ($hasTags) {
                $laneLeftRoom = max($laneLeftRoom, $dotRadius + 0.5 * $unit + $badgeMaxWidth);
            }
            $laneOrigin = $margin + $laneLeftRoom;
        }

        // Commit positions: (x, y, lane index), keyed by commit id.
        /** @var array<string, array{float, float, int}> $positions */
        $positions = [];
        foreach ($graph->commits as $order => $commit) {
            if (!isset($laneOf[$commit->branch])) {
                throw new InvalidDiagramException(\sprintf('Commit "%s" references unknown branch "%s".', $commit->id, $commit->branch));
            }
            $lane = $laneOf[$commit->branch];
            $axis = $axisOrigin + $order * $commitSpacing;
            $cross = $laneOrigin + $lane * $laneSpacing;
            $positions[$commit->id] = $ltr ? [$axis, $cross, $lane] : [$cross, $axis, $lane];
        }

        $maxX = 0.0;
        $maxY = 0.0;
        $extend = static function (float $x, float $y) use (&$maxX, &$maxY): void {
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        };

        // Edges first so dots paint over them.
        /** @var list<NodeInterface> $edges */
        $edges = [];
        foreach ($graph->commits as $commit) {
            [$cx, $cy, $cLane] = $positions[$commit->id];
            foreach ($commit->parents as $parentId) {
                if (!isset($positions[$parentId])) {
                    throw new InvalidDiagramException(\sprintf('Commit "%s" references unknown parent "%s".', $commit->id, $parentId));
                }
                [$px, $py, $pLane] = $positions[$parentId];

                if ($pLane === $cLane) {
                    $edges[] = new LineNode($px, $py, $cx, $cy, ShapeStyle::stroked($this->laneColor($theme, $cLane), $edgeWidth));
                    continue;
                }

                // Cross-lane connection, colored after the later-created (feature)
                // lane so branch-out and merge-back read as one strand. The
                // strand runs straight along the axis and turns at 90 degrees
                // near one end: near the parent when branching out to a newer
                // lane, near the child when merging back.
                $color = $this->laneColor($theme, max($pLane, $cLane));
                $data = $this->crossLanePath($ltr, $px, $py, $cx, $cy, $commitSpacing, $cLane > $pLane, self::TURN_RADIUS_FACTOR * $unit);
                $edges[] = new PathNode($data, ShapeStyle::stroked($color, $edgeWidth));
            }
        }

        // Dots, then ids and tag badges.
        /** @var list<NodeInterface> $dots */
        $dots = [];
        /** @var list<NodeInterface> $decorations */
        $decorations = [];
        foreach ($graph->commits as $commit) {
            [$x, $y, $lane] = $positions[$commit->id];
            $color = $this->laneColor($theme, $lane);

            $dots[] = new CircleNode($x, $y, $dotRadius, $commit->isMerge()
                ? new ShapeStyle(fill: $theme->backgroundColor, stroke: $color, strokeWidth: $edgeWidth)
                : ShapeStyle::filled($color));
            $extend($x + $dotRadius, $y + $dotRadius);

            $idMetrics = $this->textMeasurer->measureLine($commit->id, $smallFontSize);
            if ($ltr) {
                $idBaseline = $y + $dotRadius + 0.5 * $unit + $idMetrics->ascent;
                $decorations[] = new TextNode($x, $idBaseline, $commit->id, new TextStyle($theme->fontFamily, $smallFontSize, FontWeight::Normal, TextAnchor::Middle, $theme->mutedTextColor));
                $extend($x + $idMetrics->width / 2.0, $idBaseline + $idMetrics->height - $idMetrics->ascent);
            } else {
                $idStart = $x + $dotRadius + 0.75 * $unit;
                $idBaseline = $y + $idMetrics->ascent - $idMetrics->height / 2.0;
                $decorations[] = new TextNode($idStart, $idBaseline, $commit->id, new TextStyle($theme->fontFamily, $smallFontSize, FontWeight::Normal, TextAnchor::Start, $theme->mutedTextColor));
                $extend($idStart + $idMetrics->width, $idBaseline + $idMetrics->height - $idMetrics->ascent);
            }

            if (null !== $commit->tag) {
                $tagMetrics = $this->textMeasurer->measureLine($commit->tag, $smallFontSize);
                $badgeWidth = $tagMetrics->width + 2.0 * $badgePadX;
                if ($ltr) {
                    $badgeX = $x - $badgeWidth / 2.0;
                    $badgeY = $y - $dotRadius - 0.5 * $unit - $badgeHeight;
                } else {
                    $badgeX = $x - $dotRadius - 0.5 * $unit - $badgeWidth;
                    $badgeY = $y - $badgeHeight / 2.0;
                }
                $decorations[] = new RectNode($badgeX, $badgeY, $badgeWidth, $badgeHeight, new ShapeStyle(fill: $theme->nodeFillColor, stroke: $theme->nodeStrokeColor), cornerRadius: 0.375 * $unit);
                $decorations[] = new TextNode($badgeX + $badgeWidth / 2.0, $badgeY + $badgePadY + $tagMetrics->ascent, $commit->tag, new TextStyle($theme->fontFamily, $smallFontSize, FontWeight::Normal, TextAnchor::Middle, $theme->textColor));
                $extend($badgeX + $badgeWidth, $badgeY + $badgeHeight);
            }
        }

        // Branch labels at lane start.
        foreach ($graph->branches as $lane => $branch) {
            $metrics = $this->textMeasurer->measureLine($branch->name, $theme->fontSize, FontWeight::Bold);
            $style = new TextStyle($theme->fontFamily, $theme->fontSize, FontWeight::Bold, $ltr ? TextAnchor::End : TextAnchor::Middle, $this->laneColor($theme, $lane));
            if ($ltr) {
                $laneY = $laneOrigin + $lane * $laneSpacing;
                $baseline = $laneY + $metrics->ascent - $metrics->height / 2.0;
                $decorations[] = new TextNode($margin + $labelMaxWidth, $baseline, $branch->name, $style);
                $extend($margin + $labelMaxWidth, $baseline + $metrics->height - $metrics->ascent);
            } else {
                $laneX = $laneOrigin + $lane * $laneSpacing;
                $baseline = $margin + $titleBlock + $metrics->ascent;
                $decorations[] = new TextNode($laneX, $baseline, $branch->name, $style);
                $extend($laneX + $metrics->width / 2.0, $baseline + $metrics->height - $metrics->ascent);
            }
        }

        // Automatic branch -> color legend at the bottom left.
        if ($graph->showLegend && [] !== $graph->branches) {
            $entries = [];
            foreach ($graph->branches as $lane => $branch) {
                $entries[] = new LegendEntry($branch->name, $this->laneColor($theme, $lane));
            }
            $legend = new Legend($entries);
            [$legendWidth, $legendHeight] = $this->legends->measure($legend, $theme);
            $legendTop = $maxY + 3.0 * $unit;
            $decorations = [...$decorations, ...$this->legends->nodes($legend, $theme, $margin, $legendTop)];
            $extend($margin + $legendWidth, $legendTop + $legendHeight);
        }

        $width = max($maxX + $margin, 2.0 * $margin);
        $height = max($maxY + $margin, 2.0 * $margin);

        // Title last: centered, so it needs the final width.
        if (null !== $graph->title) {
            $width = max($width, $this->titles->measure($graph->title, $theme)->width + 2.0 * $margin);
            $decorations[] = $this->titles->node($graph->title, $theme, $width / 2.0, $margin);
        }

        return new Scene($width, $height, $theme->backgroundColor, [...$edges, ...$dots, ...$decorations], title: $graph->title?->text);
    }

    private function laneColor(Theme $theme, int $lane): string
    {
        return $theme->accentColors[$lane % \count($theme->accentColors)];
    }

    private function crossLanePath(bool $ltr, float $px, float $py, float $cx, float $cy, float $commitSpacing, bool $branchingOut, float $maxRadius): string
    {
        if ($ltr) {
            $transition = min($commitSpacing, abs($cx - $px) / 2.0);
            $anchorX = $branchingOut ? $px + $transition : $cx - $transition;
            $radius = $this->cornerRadius($maxRadius, abs($anchorX - $px), abs($cx - $anchorX), abs($cy - $py) / 2.0);
            $verticalDirection = $cy >= $py ? 1.0 : -1.0;
            $firstHorizontalDirection = $anchorX >= $px ? 1.0 : -1.0;
            $secondHorizontalDirection = $cx >= $anchorX ? 1.0 : -1.0;

            return \sprintf(
                'M %s %s H %s Q %s %s %s %s V %s Q %s %s %s %s H %s',
                PathData::number($px),
                PathData::number($py),
                PathData::number($anchorX - $firstHorizontalDirection * $radius),
                PathData::number($anchorX),
                PathData::number($py),
                PathData::number($anchorX),
                PathData::number($py + $verticalDirection * $radius),
                PathData::number($cy - $verticalDirection * $radius),
                PathData::number($anchorX),
                PathData::number($cy),
                PathData::number($anchorX + $secondHorizontalDirection * $radius),
                PathData::number($cy),
                PathData::number($cx),
            );
        }

        $transition = min($commitSpacing, abs($cy - $py) / 2.0);
        $anchorY = $branchingOut ? $py + $transition : $cy - $transition;
        $radius = $this->cornerRadius($maxRadius, abs($anchorY - $py), abs($cy - $anchorY), abs($cx - $px) / 2.0);
        $horizontalDirection = $cx >= $px ? 1.0 : -1.0;
        $firstVerticalDirection = $anchorY >= $py ? 1.0 : -1.0;
        $secondVerticalDirection = $cy >= $anchorY ? 1.0 : -1.0;

        return \sprintf(
            'M %s %s V %s Q %s %s %s %s H %s Q %s %s %s %s V %s',
            PathData::number($px),
            PathData::number($py),
            PathData::number($anchorY - $firstVerticalDirection * $radius),
            PathData::number($px),
            PathData::number($anchorY),
            PathData::number($px + $horizontalDirection * $radius),
            PathData::number($anchorY),
            PathData::number($cx - $horizontalDirection * $radius),
            PathData::number($cx),
            PathData::number($anchorY),
            PathData::number($cx),
            PathData::number($anchorY + $secondVerticalDirection * $radius),
            PathData::number($cy),
        );
    }

    private function cornerRadius(float $maxRadius, float ...$segments): float
    {
        return min($maxRadius, ...array_filter($segments, static fn (float $segment): bool => $segment > 0.0));
    }
}
