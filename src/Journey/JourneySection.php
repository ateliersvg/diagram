<?php

declare(strict_types=1);

namespace Atelier\Diagram\Journey;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class JourneySection
{
    /**
     * @param list<JourneyTask> $tasks
     */
    public function __construct(
        public string $title,
        public array $tasks,
    ) {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Journey section title must be non-empty.');
        }
        if ([] === $tasks) {
            throw new InvalidArgumentException(\sprintf('Journey section "%s" must contain at least one task.', $title));
        }
    }
}
