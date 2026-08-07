<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Activation bar on a participant lifeline.
 */
final readonly class SequenceActivation
{
    public function __construct(
        public string $participant,
        public int $firstMessageIndex,
        public int $lastMessageIndex,
    ) {
        if ('' === trim($participant)) {
            throw new InvalidArgumentException('Sequence activation participant must be non-empty.');
        }
        if ($firstMessageIndex < 0 || $lastMessageIndex < 0 || $firstMessageIndex > $lastMessageIndex) {
            throw new InvalidArgumentException('Sequence activation message range is invalid.');
        }
    }
}
