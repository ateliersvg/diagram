<?php

declare(strict_types=1);

namespace Atelier\Diagram\Mindmap;

use Atelier\Diagram\Model\DiagramModel;

final readonly class MindmapDiagram implements DiagramModel
{
    public function __construct(
        public MindmapNode $root,
    ) {
    }

    /**
     * @return list<MindmapNode>
     */
    public function nodesDepthFirst(): array
    {
        return $this->walk($this->root);
    }

    /**
     * @return list<MindmapNode>
     */
    private function walk(MindmapNode $node): array
    {
        $nodes = [$node];

        foreach ($node->children as $child) {
            $nodes = [...$nodes, ...$this->walk($child)];
        }

        return $nodes;
    }
}
