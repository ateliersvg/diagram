<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

/**
 * One significant source line with raw content preserved.
 */
final readonly class SourceLine
{
    public string $trimmed;

    public int $indent;

    public function __construct(
        public int $number,
        public string $raw,
    ) {
        $this->trimmed = trim($raw);
        $this->indent = strspn($raw, ' ');
    }

    public function containsTab(): bool
    {
        return str_contains($this->raw, "\t");
    }

    public function asLine(): Line
    {
        return new Line($this->number, $this->trimmed);
    }

    public function asRawLine(): Line
    {
        return new Line($this->number, $this->raw);
    }
}
