<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\SourceLine;

/**
 * Successful match of a named rule against a raw-source-aware line.
 */
final readonly class SourceStatementMatch
{
    /**
     * @param list<string> $captures
     */
    public function __construct(
        public SourceStatementRule $rule,
        public SourceLine $line,
        private array $captures,
    ) {
    }

    public function get(int $index): string
    {
        if (!array_key_exists($index, $this->captures)) {
            throw new InvalidArgumentException(\sprintf('Source statement "%s" has no capture %d.', $this->rule->name, $index));
        }

        return $this->captures[$index];
    }

    public function optional(int $index): ?string
    {
        if (!array_key_exists($index, $this->captures)) {
            return null;
        }

        return $this->captures[$index];
    }

    /**
     * @return list<string>
     */
    public function captures(): array
    {
        return $this->captures;
    }
}
