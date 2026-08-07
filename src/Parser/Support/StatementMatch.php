<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;

/**
 * Successful match of a named line-level statement rule.
 */
final readonly class StatementMatch
{
    /**
     * @param list<string> $captures
     */
    public function __construct(
        public StatementRule $rule,
        public Line $line,
        private array $captures,
    ) {
    }

    public function get(int $index): string
    {
        if (!array_key_exists($index, $this->captures)) {
            throw new InvalidArgumentException(\sprintf('Statement "%s" has no capture %d.', $this->rule->name, $index));
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
