<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

/**
 * Streaming line splitter that preserves byte offsets and supports Unicode
 * line separators without materializing the full line array.
 */
final class LineBreakScanner
{
    private function __construct()
    {
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    public static function read(string $source, int $offset, int $length): ?array
    {
        if ($offset >= $length) {
            return null;
        }

        if (0 === $offset && $length >= 3 && "\xEF" === $source[0] && "\xBB" === $source[1] && "\xBF" === $source[2]) {
            $offset = 3;
            if ($offset >= $length) {
                return ['', $offset];
            }
        }

        $start = $offset;

        while ($offset < $length) {
            $candidate = $offset + strcspn($source, "\r\n\v\f", $offset);
            if ($candidate < $length) {
                $breakLength = self::breakLength($source, $candidate, $length);

                return [substr($source, $start, $candidate - $start), $candidate + $breakLength];
            }

            $candidate = $offset + strcspn($source, "\xC2\xE2", $offset);
            if ($candidate >= $length) {
                return [substr($source, $start, $length - $start), $length];
            }

            $byte = ord($source[$candidate]);
            if (0xC2 === $byte && $candidate + 1 < $length && 0x85 === ord($source[$candidate + 1])) {
                return [substr($source, $start, $candidate - $start), $candidate + 2];
            }

            if (0xE2 === $byte && $candidate + 2 < $length && 0x80 === ord($source[$candidate + 1])) {
                $third = ord($source[$candidate + 2]);
                if (0xA8 === $third || 0xA9 === $third) {
                    return [substr($source, $start, $candidate - $start), $candidate + 3];
                }
            }

            $offset = $candidate + 1;
        }

        return [substr($source, $start, $length - $start), $length];
    }

    private static function breakLength(string $source, int $offset, int $length): int
    {
        if (0x0D === ord($source[$offset]) && $offset + 1 < $length && "\n" === $source[$offset + 1]) {
            return 2;
        }

        return 1;
    }
}
