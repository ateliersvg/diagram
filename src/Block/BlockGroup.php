<?php

declare(strict_types=1);

namespace Atelier\Diagram\Block;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class BlockGroup
{
    /**
     * @param non-empty-list<string> $nodeIds
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $nodeIds,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Block group id must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Block group label must be non-empty.');
        }
        if ([] === $nodeIds) {
            throw new InvalidArgumentException('Block group must contain at least one block.');
        }
    }
}
