<?php

declare(strict_types=1);

namespace Atelier\Diagram\Journey;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

final readonly class JourneyDiagram implements DiagramModel
{
    /**
     * @param list<JourneySection> $sections
     */
    public function __construct(
        public array $sections,
        public ?Title $title = null,
    ) {
        if ([] === $sections) {
            throw new InvalidArgumentException('Journey diagram must contain at least one section.');
        }
    }
}
