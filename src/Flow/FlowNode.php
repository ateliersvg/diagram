<?php

declare(strict_types=1);

namespace Atelier\Diagram\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Rectangular node in a v0 flowchart.
 */
final readonly class FlowNode
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Flow node id must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Flow node label must be non-empty.');
        }
    }
}
