<?php

declare(strict_types=1);

namespace Atelier\Diagram\Block;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class BlockNode
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $groupId = null,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Block id must be non-empty.');
        }
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Block label must be non-empty.');
        }
    }
}
