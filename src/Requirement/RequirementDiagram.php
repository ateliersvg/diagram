<?php

declare(strict_types=1);

namespace Atelier\Diagram\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;

final readonly class RequirementDiagram implements DiagramModel
{
    /**
     * @param list<RequirementNode>         $nodes
     * @param list<RequirementRelationship> $relationships
     */
    public function __construct(
        public array $nodes,
        public array $relationships = [],
    ) {
        if ([] === $nodes) {
            throw new InvalidArgumentException('Requirement diagram must contain at least one node.');
        }

        $ids = [];
        foreach ($nodes as $node) {
            if (isset($ids[$node->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate requirement diagram node id "%s".', $node->id));
            }
            $ids[$node->id] = true;
        }

        foreach ($relationships as $relationship) {
            if (!isset($ids[$relationship->from])) {
                throw new InvalidArgumentException(\sprintf('Requirement relationship references unknown node "%s".', $relationship->from));
            }
            if (!isset($ids[$relationship->to])) {
                throw new InvalidArgumentException(\sprintf('Requirement relationship references unknown node "%s".', $relationship->to));
            }
        }
    }

    /**
     * @return list<RequirementNode>
     */
    public function nodesOfKind(RequirementNodeKind $kind): array
    {
        return array_values(array_filter($this->nodes, static fn (RequirementNode $node): bool => $node->kind === $kind));
    }
}
