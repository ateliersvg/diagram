<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Small value helpers for line-oriented parsers.
 */
final class ParserValues
{
    private function __construct()
    {
    }

    public static function nonEmpty(Line $line, string $value, string $what): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw ParseErrors::syntax($line, \sprintf('%s must not be empty', $what), ParserDiagnosticCode::EmptyValue);
        }

        return $value;
    }

    public static function nonEmptyLabel(Line $line, string $value, string $what): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw ParseErrors::emptyLabel($line, $what);
        }

        return $value;
    }

    public static function optionalNonEmptyLabel(Line $line, ?string $value, string $what): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::nonEmptyLabel($line, $value, $what);
    }

    public static function optionalNonEmpty(Line $line, ?string $value, string $what): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::nonEmpty($line, $value, $what);
    }

    /**
     * @return non-empty-list<string>
     */
    public static function commaSeparated(Line $line, string $value, string $what): array
    {
        $items = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ('' === $item) {
                throw ParseErrors::syntax($line, \sprintf('%s must not contain empty items', $what), ParserDiagnosticCode::EmptyListItem);
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param non-empty-list<string> $allowed
     */
    public static function enumToken(Line $line, string $token, array $allowed, string $what): string
    {
        if (!\in_array($token, $allowed, true)) {
            throw ParseErrors::syntax($line, \sprintf('%s must be one of: %s', $what, implode(', ', $allowed)), ParserDiagnosticCode::InvalidEnum);
        }

        return $token;
    }
}
