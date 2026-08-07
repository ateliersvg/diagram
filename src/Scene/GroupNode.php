<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Group of nodes, optionally with a shared opacity.
 *
 * No transform in v1 -- layout engines emit absolute coordinates.
 */
final readonly class GroupNode implements NodeInterface
{
    /**
     * @param list<NodeInterface> $children children in paint order
     * @param float|null          $opacity  0.0 to 1.0, null for opaque
     */
    public function __construct(
        public array $children,
        public ?float $opacity = null,
    ) {
        if (null !== $opacity && ($opacity < 0.0 || $opacity > 1.0)) {
            throw new InvalidArgumentException(\sprintf('GroupNode opacity must be between 0 and 1, got %s.', $opacity));
        }
    }
}
