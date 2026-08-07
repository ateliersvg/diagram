<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\Style\ShapeStyle;

/**
 * Arbitrary path described in SVG path-data syntax -- the one geometry
 * language both SVG and Canvas (Path2D) understand verbatim.
 */
final readonly class PathNode implements NodeInterface
{
    public function __construct(
        public string $data,
        public ShapeStyle $style,
    ) {
        if ('' === trim($data)) {
            throw new InvalidArgumentException('PathNode data must be a non-empty SVG path-data string.');
        }
    }
}
