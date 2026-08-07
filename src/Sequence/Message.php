<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Horizontal message from one participant lane to another.
 */
final readonly class Message
{
    public function __construct(
        public string $from,
        public string $to,
        public string $label,
        public MessageArrow $arrow = MessageArrow::Solid,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Sequence message endpoints must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Sequence message label must be non-empty.');
        }
    }
}
