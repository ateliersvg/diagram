<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

final readonly class C4Diagram implements DiagramModel
{
    /**
     * @param list<C4Boundary>     $boundaries
     * @param list<C4Element>      $elements
     * @param list<C4Relationship> $relationships
     */
    public function __construct(
        public C4View $view,
        public array $boundaries,
        public array $elements,
        public array $relationships = [],
        public ?Title $title = null,
    ) {
        if ([] === $elements) {
            throw new InvalidArgumentException('C4 diagram must contain at least one element.');
        }

        $boundaryIds = [];
        foreach ($boundaries as $boundary) {
            if (isset($boundaryIds[$boundary->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate C4 boundary id "%s".', $boundary->id));
            }
            $boundaryIds[$boundary->id] = true;
        }

        $elementIds = [];
        foreach ($elements as $element) {
            if (isset($elementIds[$element->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate C4 element id "%s".', $element->id));
            }
            if (null !== $element->boundaryId && !isset($boundaryIds[$element->boundaryId])) {
                throw new InvalidArgumentException(\sprintf('C4 element "%s" references unknown boundary "%s".', $element->id, $element->boundaryId));
            }
            $elementIds[$element->id] = true;
        }

        foreach ($relationships as $relationship) {
            if (!isset($elementIds[$relationship->from])) {
                throw new InvalidArgumentException(\sprintf('C4 relationship references unknown element "%s".', $relationship->from));
            }
            if (!isset($elementIds[$relationship->to])) {
                throw new InvalidArgumentException(\sprintf('C4 relationship references unknown element "%s".', $relationship->to));
            }
        }
    }
}
