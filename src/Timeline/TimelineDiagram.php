<?php

declare(strict_types=1);

namespace Atelier\Diagram\Timeline;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

final readonly class TimelineDiagram implements DiagramModel
{
    /**
     * @param list<TimelineSection> $sections
     */
    public function __construct(
        public array $sections,
        public ?Title $title = null,
    ) {
        if ([] === $sections) {
            throw new InvalidArgumentException('Timeline diagram must contain at least one section.');
        }
    }
}
