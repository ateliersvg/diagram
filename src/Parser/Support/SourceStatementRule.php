<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\SourceLine;

/**
 * Named regex rule for source-line parsers that need raw content elsewhere.
 */
final readonly class SourceStatementRule
{
    private function __construct(
        public string $name,
        public string $pattern,
    ) {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Source statement rule name must be non-empty.');
        }
        if ('' === trim($pattern)) {
            throw new InvalidArgumentException('Source statement rule pattern must be non-empty.');
        }
        if (false === @preg_match($pattern, '')) {
            throw new InvalidArgumentException(\sprintf('Source statement rule "%s" pattern is invalid.', $name));
        }
    }

    public static function regex(string $name, string $pattern): self
    {
        return new self($name, $pattern);
    }

    public function match(SourceLine $line): ?SourceStatementMatch
    {
        if (1 !== preg_match($this->pattern, $line->trimmed, $matches)) {
            return null;
        }

        return new SourceStatementMatch($this, $line, array_values($matches));
    }
}
