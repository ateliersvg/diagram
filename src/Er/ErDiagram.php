<?php

declare(strict_types=1);

namespace Atelier\Diagram\Er;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;

final readonly class ErDiagram implements DiagramModel
{
    /**
     * @param list<ErEntity>       $entities
     * @param list<ErRelationship> $relationships
     */
    public function __construct(
        public array $entities,
        public array $relationships = [],
    ) {
        if ([] === $entities) {
            throw new InvalidArgumentException('ER diagram must contain at least one entity.');
        }

        $ids = [];
        foreach ($entities as $entity) {
            if (isset($ids[$entity->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate ER entity id "%s".', $entity->id));
            }
            $ids[$entity->id] = true;
        }

        foreach ($relationships as $relationship) {
            if (!isset($ids[$relationship->from])) {
                throw new InvalidArgumentException(\sprintf('ER relationship references unknown entity "%s".', $relationship->from));
            }
            if (!isset($ids[$relationship->to])) {
                throw new InvalidArgumentException(\sprintf('ER relationship references unknown entity "%s".', $relationship->to));
            }
        }
    }
}
