<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Vertical block spanning one or more messages in a sequence diagram.
 */
final readonly class SequenceBlock
{
    /**
     * @param list<SequenceBlockBranch> $branches
     */
    public function __construct(
        public SequenceBlockKind $kind,
        public string $label,
        public int $firstMessageIndex,
        public int $lastMessageIndex,
        public array $branches = [],
    ) {
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Sequence block label must be non-empty.');
        }
        if ($firstMessageIndex < 0 || $lastMessageIndex < 0 || $firstMessageIndex > $lastMessageIndex) {
            throw new InvalidArgumentException('Sequence block message range is invalid.');
        }
        foreach ($branches as $branch) {
            if ($branch->firstMessageIndex < $firstMessageIndex || $branch->lastMessageIndex > $lastMessageIndex) {
                throw new InvalidArgumentException('Sequence block branch must be inside its block range.');
            }
        }
    }
}
