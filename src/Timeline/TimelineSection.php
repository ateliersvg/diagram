<?php

declare(strict_types=1);

namespace Atelier\Diagram\Timeline;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class TimelineSection
{
    /**
     * @param list<TimelineEvent> $events
     */
    public function __construct(
        public string $title,
        public array $events,
    ) {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Timeline section title must be non-empty.');
        }
        if ([] === $events) {
            throw new InvalidArgumentException(\sprintf('Timeline section "%s" must contain at least one event.', $title));
        }
    }
}
