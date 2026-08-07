<?php

declare(strict_types=1);

namespace Atelier\Diagram\State;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * One state of a state diagram.
 *
 * The label defaults to the id. Initial/final pseudo-states are not
 * states -- they are addressed by StateDiagram::INITIAL / ::FINAL.
 */
final readonly class State
{
    public string $label;

    public function __construct(
        public string $id,
        ?string $label = null,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('State id must be a non-empty string.');
        }
        if (null !== $label && '' === trim($label)) {
            throw new InvalidArgumentException(\sprintf('State "%s" label must be a non-empty string.', $id));
        }

        $this->label = $label ?? $id;
    }
}
