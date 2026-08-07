<?php

declare(strict_types=1);

namespace Atelier\Diagram\Timeline;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class TimelineEvent
{
    public function __construct(
        public string $label,
        public string $date,
    ) {
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Timeline event label must be non-empty.');
        }
        if ('' === trim($date)) {
            throw new InvalidArgumentException('Timeline event date must be non-empty.');
        }
    }
}
