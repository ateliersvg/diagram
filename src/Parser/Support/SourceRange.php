<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

final readonly class SourceRange
{
    public function __construct(
        public Line $start,
        public Line $end,
    ) {
    }

    public function contains(int $lineNumber): bool
    {
        return $lineNumber >= $this->start->number && $lineNumber <= $this->end->number;
    }

    public function lineCount(): int
    {
        return $this->end->number - $this->start->number + 1;
    }
}
