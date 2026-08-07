<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

/**
 * Stable human-readable rendering for parser diagnostics.
 */
final class ParserDiagnosticFormatter
{
    private function __construct()
    {
    }

    public static function format(ParserDiagnostic $diagnostic): string
    {
        $summary = $diagnostic->message;
        if (null !== $diagnostic->code) {
            $summary .= \sprintf(' [%s]', $diagnostic->code);
        }

        $summary .= \sprintf(' at line %d', $diagnostic->span->startLine);

        if (null === $diagnostic->source) {
            return $summary;
        }

        $lineNumber = (string) $diagnostic->source->lineNumber;
        $prefix = $lineNumber.' | ';
        $markerPrefix = str_repeat(' ', strlen($lineNumber)).' | ';
        $sourceLine = '' === $diagnostic->source->content ? rtrim($prefix) : $prefix.$diagnostic->source->content;

        return implode("\n", [
            $summary,
            $sourceLine,
            $markerPrefix.self::marker($diagnostic),
        ]);
    }

    private static function marker(ParserDiagnostic $diagnostic): string
    {
        $source = $diagnostic->source;
        if (null === $source) {
            return '^';
        }

        if ($diagnostic->span->startLine !== $source->lineNumber) {
            return '^';
        }

        $startColumn = max(1, $diagnostic->span->startColumn);
        $width = $diagnostic->span->isSingleLine()
            ? max(1, self::displayWidth($source->content, $startColumn, $diagnostic->span->endColumn))
            : max(1, self::contentLength($source->content));

        return str_repeat(' ', self::displayOffset($source->content, $startColumn)).str_repeat('^', $width);
    }

    private static function displayOffset(string $content, int $startColumn): int
    {
        $prefixColumns = max(0, $startColumn - 1);
        if (!function_exists('mb_substr') || !function_exists('mb_strlen')) {
            return $prefixColumns;
        }

        return mb_strlen(mb_substr($content, 0, $prefixColumns, 'UTF-8'), 'UTF-8');
    }

    private static function displayWidth(string $content, int $startColumn, int $endColumn): int
    {
        $startColumns = max(0, $startColumn - 1);
        $lengthColumns = max(0, $endColumn - $startColumn);
        if (!function_exists('mb_substr') || !function_exists('mb_strlen')) {
            return max(1, min(strlen($content) + 1, $endColumn) - $startColumn);
        }

        return mb_strlen(mb_substr($content, $startColumns, $lengthColumns, 'UTF-8'), 'UTF-8');
    }

    private static function contentLength(string $content): int
    {
        if (!function_exists('mb_strlen')) {
            return strlen($content);
        }

        return mb_strlen($content, 'UTF-8');
    }
}
