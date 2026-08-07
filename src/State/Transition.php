<?php

declare(strict_types=1);

namespace Atelier\Diagram\State;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

/**
 * Directed transition between two states.
 *
 * `from` may be StateDiagram::INITIAL and `to` may be StateDiagram::FINAL.
 * Self-transitions (from === to) are allowed.
 */
final readonly class Transition
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Label $label = null,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Transition endpoints must be non-empty state ids.');
        }
    }
}
