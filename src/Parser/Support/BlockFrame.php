<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Mutable frame for a block currently open in a line-based grammar.
 *
 * @internal
 */
final class BlockFrame
{
    /**
     * @var array<string, true>
     */
    private array $touched = [];

    public function __construct(
        public readonly string $kind,
        public readonly string $id,
        public readonly string $label,
        public readonly Line $startLine,
        public readonly ?int $startIndex,
        public readonly ?string $parentId,
        public readonly int $depth,
    ) {
    }

    public function touch(string $value): void
    {
        $this->touched[$value] = true;
    }

    /**
     * @return list<string>
     */
    public function touchedValues(): array
    {
        return array_keys($this->touched);
    }
}
