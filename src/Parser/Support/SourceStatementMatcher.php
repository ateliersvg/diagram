<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\SourceLine;

/**
 * Ordered matching for indentation-sensitive source-line parsers.
 */
final readonly class SourceStatementMatcher
{
    public function __construct(
        private SourceLine $line,
    ) {
    }

    public static function for(SourceLine $line): self
    {
        return new self($line);
    }

    public function matchRule(SourceStatementRule $rule): ?SourceStatementMatch
    {
        return $rule->match($this->line);
    }
}
