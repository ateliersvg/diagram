<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Small wrapper for ordered line-level regex matching.
 */
final readonly class StatementMatcher
{
    public function __construct(
        private Line $line,
    ) {
    }

    public static function for(Line $line): self
    {
        return new self($line);
    }

    /**
     * @return list<string>|null regex matches, or null when the pattern does not match
     */
    public function match(string $pattern): ?array
    {
        if (1 !== preg_match($pattern, $this->line->content, $matches)) {
            return null;
        }

        return array_values($matches);
    }

    public function matchRule(StatementRule $rule): ?StatementMatch
    {
        return $rule->match($this->line);
    }
}
