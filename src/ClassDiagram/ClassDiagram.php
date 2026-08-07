<?php

declare(strict_types=1);

namespace Atelier\Diagram\ClassDiagram;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;

final readonly class ClassDiagram implements DiagramModel
{
    /**
     * @param list<ClassBox>      $classes
     * @param list<ClassRelation> $relations
     */
    public function __construct(
        public array $classes,
        public array $relations = [],
    ) {
        if ([] === $classes) {
            throw new InvalidArgumentException('Class diagram must contain at least one class.');
        }

        $ids = [];
        foreach ($classes as $class) {
            if (isset($ids[$class->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate class id "%s".', $class->id));
            }
            $ids[$class->id] = true;
        }

        foreach ($relations as $relation) {
            if (!isset($ids[$relation->from])) {
                throw new InvalidArgumentException(\sprintf('Class relation references unknown class "%s".', $relation->from));
            }
            if (!isset($ids[$relation->to])) {
                throw new InvalidArgumentException(\sprintf('Class relation references unknown class "%s".', $relation->to));
            }
        }
    }
}
