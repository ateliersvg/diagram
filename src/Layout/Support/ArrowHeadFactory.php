<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Model\ArrowHead;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\Style\ShapeStyle;

/**
 * Builds arrowhead PathNodes from Model\ArrowHead.
 *
 * The Scene has no marker concept, so layout engines emit arrowheads as
 * explicit paths: a filled triangle for Arrow, a stroked chevron for Open,
 * nothing for None. The tip sits at (tipX, tipY) and the head points along
 * the (dx, dy) direction; a degenerate direction falls back to pointing
 * down.
 *
 * @internal
 */
final class ArrowHeadFactory
{
    /**
     * Builds the head for any ArrowHead kind; None yields null.
     */
    public function node(ArrowHead $head, float $tipX, float $tipY, float $dx, float $dy, float $length, float $halfWidth, string $color, float $strokeWidth = 1.0): ?PathNode
    {
        return match ($head) {
            ArrowHead::None => null,
            ArrowHead::Arrow => $this->arrow($tipX, $tipY, $dx, $dy, $length, $halfWidth, $color),
            ArrowHead::Open => $this->open($tipX, $tipY, $dx, $dy, $length, $halfWidth, $color, $strokeWidth),
        };
    }

    /**
     * Filled triangle (ArrowHead::Arrow).
     */
    public function arrow(float $tipX, float $tipY, float $dx, float $dy, float $length, float $halfWidth, string $color): PathNode
    {
        [$left, $right] = $this->base($tipX, $tipY, $dx, $dy, $length, $halfWidth);

        return new PathNode(\sprintf(
            'M %s %s L %s %s L %s %s Z',
            PathData::number($tipX),
            PathData::number($tipY),
            PathData::number($left[0]),
            PathData::number($left[1]),
            PathData::number($right[0]),
            PathData::number($right[1]),
        ), ShapeStyle::filled($color));
    }

    /**
     * Stroked chevron (ArrowHead::Open).
     */
    public function open(float $tipX, float $tipY, float $dx, float $dy, float $length, float $halfWidth, string $color, float $strokeWidth = 1.0): PathNode
    {
        [$left, $right] = $this->base($tipX, $tipY, $dx, $dy, $length, $halfWidth);

        return new PathNode(\sprintf(
            'M %s %s L %s %s L %s %s',
            PathData::number($left[0]),
            PathData::number($left[1]),
            PathData::number($tipX),
            PathData::number($tipY),
            PathData::number($right[0]),
            PathData::number($right[1]),
        ), ShapeStyle::stroked($color, $strokeWidth));
    }

    /**
     * The two base corners of a head whose tip is at (tipX, tipY).
     *
     * @return array{array{float, float}, array{float, float}}
     */
    private function base(float $tipX, float $tipY, float $dx, float $dy, float $length, float $halfWidth): array
    {
        $norm = hypot($dx, $dy);
        if ($norm < 1e-9) {
            $dx = 0.0;
            $dy = 1.0;
            $norm = 1.0;
        }
        $ux = $dx / $norm;
        $uy = $dy / $norm;
        $bx = $tipX - $length * $ux;
        $by = $tipY - $length * $uy;

        return [
            [$bx - $halfWidth * $uy, $by + $halfWidth * $ux],
            [$bx + $halfWidth * $uy, $by - $halfWidth * $ux],
        ];
    }
}
