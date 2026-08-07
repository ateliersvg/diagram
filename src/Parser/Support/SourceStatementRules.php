<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Shared catalog for source-line statement rules.
 */
final class SourceStatementRules
{
    /** @var array<string, SourceStatementRule> */
    private static array $rules = [];

    private function __construct()
    {
    }

    public static function get(string $name, string $pattern): SourceStatementRule
    {
        if (isset(self::$rules[$name])) {
            if (self::$rules[$name]->pattern !== $pattern) {
                throw new InvalidArgumentException(\sprintf('Source statement rule "%s" is already registered with a different pattern.', $name));
            }

            return self::$rules[$name];
        }

        return self::$rules[$name] = SourceStatementRule::regex($name, $pattern);
    }

    /**
     * @return array<string, SourceStatementRule>
     */
    public static function all(): array
    {
        return self::$rules;
    }
}
