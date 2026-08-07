<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Reads and validates the first significant line of a Mermaid source.
 */
final class HeaderParser
{
    private function __construct()
    {
    }

    public static function keyword(string $content): string
    {
        $position = \strcspn($content, " \t\r\n\f\v");

        return substr($content, 0, $position);
    }

    /**
     * @param non-empty-list<string> $headers
     */
    public static function exact(Line $line, array $headers, string $expected): void
    {
        if (!\in_array($line->content, $headers, true)) {
            throw ParseErrors::expectedHeader($line, $expected);
        }
    }

    public static function firstSignificantLine(string $source, ?ParserInputLimits $limits = null): HeaderMatch
    {
        $limits ??= ParserInputLimits::default();

        $sourceBytes = strlen($source);
        if ($sourceBytes > $limits->maxSourceBytes) {
            throw ParseErrors::sourceTooLarge($sourceBytes, $limits->maxSourceBytes);
        }

        $number = 1;
        $offset = 0;
        $length = strlen($source);

        while (null !== ($line = LineBreakScanner::read($source, $offset, $length))) {
            [$raw, $offset] = $line;

            if (strlen($raw) > $limits->maxLineBytes) {
                throw ParseErrors::lineTooLong($number, $raw, $limits->maxLineBytes);
            }

            $content = trim($raw);
            if ('' !== $content && !str_starts_with($content, '%%')) {
                return new HeaderMatch(new Line($number, $content), $offset);
            }

            ++$number;
        }

        throw ParseErrors::cannotDetectDiagramType();
    }
}
