<?php

declare(strict_types=1);

namespace Atelier\Diagram\Journey;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class JourneyTask
{
    /**
     * @param non-empty-list<JourneyActor> $actors
     */
    public function __construct(
        public string $text,
        public int $score,
        public array $actors,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('Journey task text must be non-empty.');
        }
        if ($score < 1 || $score > 5) {
            throw new InvalidArgumentException(\sprintf('Journey task score must be between 1 and 5, got %d.', $score));
        }
        if ([] === $actors) {
            throw new InvalidArgumentException('Journey task must declare at least one actor.');
        }
    }
}
