<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Branch inside a sequence block, currently used by alt/par.
 */
final readonly class SequenceBlockBranch
{
    public function __construct(
        public string $label,
        public int $firstMessageIndex,
        public int $lastMessageIndex,
    ) {
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Sequence block branch label must be non-empty.');
        }
        if ($firstMessageIndex < 0 || $lastMessageIndex < 0 || $firstMessageIndex > $lastMessageIndex) {
            throw new InvalidArgumentException('Sequence block branch message range is invalid.');
        }
    }
}
