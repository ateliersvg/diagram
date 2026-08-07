<?php

declare(strict_types=1);

namespace Atelier\Diagram\Mindmap;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class MindmapNode
{
    /**
     * @param list<MindmapNode> $children
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $children = [],
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Mindmap node id must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Mindmap node label must be non-empty.');
        }
    }
}
