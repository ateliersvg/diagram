<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

/**
 * One significant source line, as produced by LineStream.
 */
final readonly class Line
{
    /**
     * @param int    $number  1-based line number in the original source
     * @param string $content trimmed line content
     */
    public function __construct(
        public int $number,
        public string $content,
    ) {
    }
}
