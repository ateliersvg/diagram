<?php

declare(strict_types=1);

namespace Atelier\Diagram\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

/**
 * Directed edge between two flowchart nodes.
 */
final readonly class FlowEdge
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Label $label = null,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Flow edge endpoints must be non-empty.');
        }
    }
}
