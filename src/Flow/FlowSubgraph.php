<?php

declare(strict_types=1);

namespace Atelier\Diagram\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Visual cluster grouping flowchart nodes.
 */
final readonly class FlowSubgraph
{
    /**
     * @param non-empty-list<string> $nodeIds
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $nodeIds,
        public ?string $parentId = null,
        public int $depth = 0,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Flow subgraph id must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Flow subgraph label must be non-empty.');
        }
        if ([] === $nodeIds) {
            throw new InvalidArgumentException('Flow subgraph must contain at least one node.');
        }
        if ($depth < 0) {
            throw new InvalidArgumentException('Flow subgraph depth must not be negative.');
        }
    }
}
