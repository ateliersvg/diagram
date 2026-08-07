<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Closed block with source range and parser-specific payload.
 */
final readonly class BlockSpan
{
    /**
     * @param list<string> $touchedValues
     */
    public function __construct(
        public string $kind,
        public string $id,
        public string $label,
        public Line $startLine,
        public Line $endLine,
        public ?int $startIndex,
        public ?string $parentId,
        public int $depth,
        public array $touchedValues,
    ) {
    }

    public function range(): SourceRange
    {
        return new SourceRange($this->startLine, $this->endLine);
    }
}
