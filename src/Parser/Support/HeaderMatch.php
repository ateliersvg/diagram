<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * First significant line plus the byte offset immediately after it.
 */
final readonly class HeaderMatch
{
    public function __construct(
        public Line $line,
        public int $nextOffset,
    ) {
    }
}
