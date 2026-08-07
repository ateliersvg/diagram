<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Small deterministic parser for `name: "value"` attribute lists.
 */
final class QuotedAttributeList
{
    private function __construct()
    {
    }

    /**
     * @param non-empty-list<string> $allowedNames
     *
     * @return array<string, string>
     */
    public static function parse(Line $line, string $source, array $allowedNames, string $invalidMessage, string $duplicateMessagePrefix): array
    {
        $attributes = [];
        $offset = 0;
        $length = strlen($source);

        while (true) {
            $offset = self::skipWhitespace($source, $offset, $length);
            if ($offset >= $length) {
                return $attributes;
            }

            $nameStart = $offset;
            if (!self::isNameStart($source[$offset])) {
                throw ParseErrors::syntax($line, $invalidMessage);
            }
            ++$offset;

            while ($offset < $length && self::isNameChar($source[$offset])) {
                ++$offset;
            }

            $name = substr($source, $nameStart, $offset - $nameStart);
            if ($offset >= $length || ':' !== $source[$offset] || !\in_array($name, $allowedNames, true)) {
                throw ParseErrors::syntax($line, $invalidMessage);
            }
            ++$offset;

            $offset = self::skipWhitespace($source, $offset, $length);
            if ($offset >= $length || '"' !== $source[$offset]) {
                throw ParseErrors::syntax($line, $invalidMessage);
            }
            ++$offset;

            $valueStart = $offset;
            while ($offset < $length && '"' !== $source[$offset]) {
                ++$offset;
            }

            if ($offset >= $length) {
                throw ParseErrors::syntax($line, $invalidMessage);
            }

            $value = substr($source, $valueStart, $offset - $valueStart);
            ++$offset;

            if ($offset < $length && !self::isWhitespace($source[$offset])) {
                throw ParseErrors::syntax($line, $invalidMessage);
            }

            if (array_key_exists($name, $attributes)) {
                throw ParseErrors::syntax($line, \sprintf('%s "%s"', $duplicateMessagePrefix, $name));
            }

            $attributes[$name] = $value;
        }
    }

    private static function skipWhitespace(string $source, int $offset, int $length): int
    {
        while ($offset < $length && self::isWhitespace($source[$offset])) {
            ++$offset;
        }

        return $offset;
    }

    private static function isWhitespace(string $char): bool
    {
        return ' ' === $char || "\t" === $char;
    }

    private static function isNameStart(string $char): bool
    {
        return ('a' <= $char && $char <= 'z') || ('A' <= $char && $char <= 'Z') || '_' === $char;
    }

    private static function isNameChar(string $char): bool
    {
        return self::isNameStart($char) || ('0' <= $char && $char <= '9');
    }
}
