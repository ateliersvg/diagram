<?php

declare(strict_types=1);

namespace Atelier\Diagram\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Title;

/**
 * Minimal directed flowchart model.
 */
final readonly class Flowchart implements DiagramModel
{
    /**
     * @param non-empty-list<FlowNode> $nodes
     * @param list<FlowEdge>           $edges
     * @param list<FlowSubgraph>       $subgraphs
     */
    public function __construct(
        public Direction $direction,
        public array $nodes,
        public array $edges,
        public ?Title $title = null,
        public array $subgraphs = [],
    ) {
        if ([] === $nodes) {
            throw new InvalidArgumentException('Flowchart must contain at least one node.');
        }

        $known = [];
        foreach ($nodes as $node) {
            if (isset($known[$node->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate flow node id "%s".', $node->id));
            }
            $known[$node->id] = true;
        }

        foreach ($edges as $edge) {
            if (!isset($known[$edge->from])) {
                throw new InvalidArgumentException(\sprintf('Flow edge references unknown node "%s".', $edge->from));
            }
            if (!isset($known[$edge->to])) {
                throw new InvalidArgumentException(\sprintf('Flow edge references unknown node "%s".', $edge->to));
            }
        }

        $subgraphIds = [];
        foreach ($subgraphs as $subgraph) {
            if (isset($subgraphIds[$subgraph->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate flow subgraph id "%s".', $subgraph->id));
            }
            $subgraphIds[$subgraph->id] = true;

            foreach ($subgraph->nodeIds as $nodeId) {
                if (!isset($known[$nodeId])) {
                    throw new InvalidArgumentException(\sprintf('Flow subgraph "%s" references unknown node "%s".', $subgraph->id, $nodeId));
                }
            }
        }

        foreach ($subgraphs as $subgraph) {
            if (null !== $subgraph->parentId && !isset($subgraphIds[$subgraph->parentId])) {
                throw new InvalidArgumentException(\sprintf('Flow subgraph "%s" references unknown parent subgraph "%s".', $subgraph->id, $subgraph->parentId));
            }
        }
    }
}
