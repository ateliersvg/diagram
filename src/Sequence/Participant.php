<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Actor or system lane in a sequence diagram.
 */
final readonly class Participant
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Sequence participant id must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Sequence participant label must be non-empty.');
        }
    }
}
