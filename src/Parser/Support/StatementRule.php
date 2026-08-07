<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;

/**
 * Named regex rule for one line-level parser statement.
 */
final readonly class StatementRule
{
    private function __construct(
        public string $name,
        public string $pattern,
    ) {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Statement rule name must be non-empty.');
        }
        if ('' === trim($pattern)) {
            throw new InvalidArgumentException('Statement rule pattern must be non-empty.');
        }
        if (false === @preg_match($pattern, '')) {
            throw new InvalidArgumentException(\sprintf('Statement rule "%s" pattern is invalid.', $name));
        }
    }

    public static function regex(string $name, string $pattern): self
    {
        return new self($name, $pattern);
    }

    public function match(Line $line): ?StatementMatch
    {
        if (1 !== preg_match($this->pattern, $line->content, $matches)) {
            return null;
        }

        return new StatementMatch($this, $line, array_values($matches));
    }
}
