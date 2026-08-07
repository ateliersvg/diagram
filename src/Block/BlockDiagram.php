<?php

declare(strict_types=1);

namespace Atelier\Diagram\Block;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

final readonly class BlockDiagram implements DiagramModel
{
    /**
     * @param non-empty-list<BlockNode> $nodes
     * @param list<BlockGroup>          $groups
     * @param list<BlockRelationship>   $relationships
     */
    public function __construct(
        public array $nodes,
        public array $groups = [],
        public array $relationships = [],
        public ?Title $title = null,
    ) {
        if ([] === $nodes) {
            throw new InvalidArgumentException('Block diagram must contain at least one block.');
        }

        $knownNodes = [];
        foreach ($nodes as $node) {
            if (isset($knownNodes[$node->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate block id "%s".', $node->id));
            }
            $knownNodes[$node->id] = true;
        }

        $knownGroups = [];
        foreach ($groups as $group) {
            if (isset($knownGroups[$group->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate block group id "%s".', $group->id));
            }
            $knownGroups[$group->id] = true;
            foreach ($group->nodeIds as $nodeId) {
                if (!isset($knownNodes[$nodeId])) {
                    throw new InvalidArgumentException(\sprintf('Block group "%s" references unknown block "%s".', $group->id, $nodeId));
                }
            }
        }

        foreach ($nodes as $node) {
            if (null !== $node->groupId && !isset($knownGroups[$node->groupId])) {
                throw new InvalidArgumentException(\sprintf('Block "%s" references unknown group "%s".', $node->id, $node->groupId));
            }
        }

        foreach ($relationships as $relationship) {
            if (!isset($knownNodes[$relationship->from])) {
                throw new InvalidArgumentException(\sprintf('Block relationship references unknown block "%s".', $relationship->from));
            }
            if (!isset($knownNodes[$relationship->to])) {
                throw new InvalidArgumentException(\sprintf('Block relationship references unknown block "%s".', $relationship->to));
            }
        }
    }
}
