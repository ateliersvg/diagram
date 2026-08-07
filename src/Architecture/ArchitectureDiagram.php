<?php

declare(strict_types=1);

namespace Atelier\Diagram\Architecture;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

final readonly class ArchitectureDiagram implements DiagramModel
{
    /**
     * @param list<ArchitectureGroup>        $groups
     * @param list<ArchitectureNode>         $nodes
     * @param list<ArchitectureRelationship> $relationships
     */
    public function __construct(
        public array $groups,
        public array $nodes,
        public array $relationships = [],
        public ?Title $title = null,
    ) {
        if ([] === $nodes) {
            throw new InvalidArgumentException('Architecture diagram must contain at least one node.');
        }

        $groupIds = [];
        foreach ($groups as $group) {
            if (isset($groupIds[$group->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate architecture group id "%s".', $group->id));
            }
            $groupIds[$group->id] = true;
        }

        $nodeIds = [];
        foreach ($nodes as $node) {
            if (isset($nodeIds[$node->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate architecture node id "%s".', $node->id));
            }
            if (null !== $node->groupId && !isset($groupIds[$node->groupId])) {
                throw new InvalidArgumentException(\sprintf('Architecture node "%s" references unknown group "%s".', $node->id, $node->groupId));
            }
            $nodeIds[$node->id] = true;
        }

        foreach ($relationships as $relationship) {
            if (!isset($nodeIds[$relationship->from])) {
                throw new InvalidArgumentException(\sprintf('Architecture relationship references unknown node "%s".', $relationship->from));
            }
            if (!isset($nodeIds[$relationship->to])) {
                throw new InvalidArgumentException(\sprintf('Architecture relationship references unknown node "%s".', $relationship->to));
            }
        }
    }
}
