<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Shared catalog for named parser statement rules.
 */
final class StatementRules
{
    /**
     * @var array<string, StatementRule>
     */
    private static array $rules = [];

    private function __construct()
    {
    }

    public static function get(string $name, string $pattern): StatementRule
    {
        $existing = self::$rules[$name] ?? null;
        if (null !== $existing) {
            if ($existing->pattern !== $pattern) {
                throw new InvalidArgumentException(\sprintf('Statement rule "%s" is already registered with a different pattern.', $name));
            }

            return $existing;
        }

        return self::$rules[$name] = StatementRule::regex($name, $pattern);
    }

    /**
     * @return array<string, StatementRule>
     */
    public static function all(): array
    {
        ksort(self::$rules);

        return self::$rules;
    }
}
