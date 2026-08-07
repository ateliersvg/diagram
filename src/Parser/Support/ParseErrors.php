<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;

/**
 * Central constructors for ParseException messages.
 */
final class ParseErrors
{
    private function __construct()
    {
    }

    public static function expectedHeader(Line $line, string $expected): ParseException
    {
        return self::line(\sprintf('Expected %s header', $expected), $line, ParserDiagnosticCode::ExpectedHeader);
    }

    public static function missingHeader(string $expected): ParseException
    {
        return new ParseException(
            \sprintf('Missing %s header', $expected),
            1,
            '',
            null,
            ParserDiagnostic::error(\sprintf('Missing %s header', $expected), SourceSpan::forLine(1, ''), ParserDiagnosticCode::MissingHeader->value, SourceExcerpt::fromSource(1, '')),
        );
    }

    public static function emptyInput(string $expected): ParseException
    {
        return new ParseException(
            \sprintf('Expected %s header, got empty input', $expected),
            1,
            '',
            null,
            ParserDiagnostic::error(\sprintf('Expected %s header, got empty input', $expected), SourceSpan::forLine(1, ''), ParserDiagnosticCode::EmptyInput->value, SourceExcerpt::fromSource(1, '')),
        );
    }

    public static function sourceTooLarge(int $sourceBytes, int $maxBytes): ParseException
    {
        $message = \sprintf('Parser source exceeds maximum size of %d bytes, got %d bytes', $maxBytes, $sourceBytes);

        return new ParseException(
            $message,
            1,
            '',
            null,
            ParserDiagnostic::error($message, SourceSpan::forLine(1, ''), ParserDiagnosticCode::SourceTooLarge->value, SourceExcerpt::fromSource(1, '')),
        );
    }

    public static function lineTooLong(int $lineNumber, string $line, int $maxBytes): ParseException
    {
        $message = \sprintf('Parser source line exceeds maximum length of %d bytes', $maxBytes);
        $sourceLine = self::truncateSourceLine($line);

        return new ParseException(
            $message,
            $lineNumber,
            $sourceLine,
            null,
            ParserDiagnostic::error($message, SourceSpan::forLine($lineNumber, $sourceLine), ParserDiagnosticCode::LineTooLong->value, SourceExcerpt::fromSource($lineNumber, $sourceLine)),
        );
    }

    public static function unknownHeader(Line $line, string $expected): ParseException
    {
        return self::line(\sprintf('Unknown diagram header, expected %s', $expected), $line, ParserDiagnosticCode::UnknownHeader);
    }

    public static function unknownDiagramHeader(Line $line): ParseException
    {
        return self::line('Unknown diagram header', $line, ParserDiagnosticCode::UnknownHeader);
    }

    public static function cannotDetectDiagramType(): ParseException
    {
        return new ParseException(
            'Cannot detect the diagram type of empty input',
            1,
            '',
            null,
            ParserDiagnostic::error('Cannot detect the diagram type of empty input', SourceSpan::forLine(1, ''), ParserDiagnosticCode::EmptyInput->value, SourceExcerpt::fromSource(1, '')),
        );
    }

    public static function unsupported(Line $line, string $grammar): ParseException
    {
        return self::line(\sprintf('Unsupported %s syntax', $grammar), $line, ParserDiagnosticCode::UnsupportedSyntax);
    }

    public static function syntax(Line $line, string $message, ParserDiagnosticCode $code = ParserDiagnosticCode::SyntaxError, ?\Throwable $previous = null): ParseException
    {
        return self::line($message, $line, $code, $previous);
    }

    public static function sourceSyntax(SourceLine $line, string $message, ParserDiagnosticCode $code = ParserDiagnosticCode::SyntaxError, ?\Throwable $previous = null): ParseException
    {
        return new ParseException(
            $message,
            $line->number,
            $line->raw,
            $previous,
            ParserDiagnostic::forSourceLine($message, $line, $code->value),
        );
    }

    public static function emptyLabel(Line $line, string $what): ParseException
    {
        return self::line(\sprintf('%s label must not be empty', $what), $line, ParserDiagnosticCode::EmptyLabel);
    }

    public static function wrap(Line $line, \Throwable $exception): ParseException
    {
        return self::line(rtrim($exception->getMessage(), '.'), $line, ParserDiagnosticCode::SemanticError, $exception);
    }

    public static function unexpectedBlockEnd(Line $line, string $grammar): ParseException
    {
        return self::line(\sprintf('Unexpected %s block end', $grammar), $line, ParserDiagnosticCode::UnexpectedBlockEnd);
    }

    public static function expectedOpenBlock(Line $line, string $grammar, string $expectedKind): ParseException
    {
        return self::line(\sprintf('Expected open %s %s block', $grammar, $expectedKind), $line, ParserDiagnosticCode::ExpectedOpenBlock);
    }

    public static function expectedBlockKind(Line $line, string $grammar, string $expectedKind, string $actualKind): ParseException
    {
        return self::line(\sprintf('Expected %s %s block, got %s block', $grammar, $expectedKind, $actualKind), $line, ParserDiagnosticCode::ExpectedBlockKind);
    }

    public static function unclosedBlock(Line $line, string $what, ?Line $endLine = null): ParseException
    {
        $message = \sprintf('Unclosed %s', $what);
        $endLine ??= $line;

        return new ParseException(
            $message,
            $line->number,
            $line->content,
            null,
            ParserDiagnostic::error(
                $message,
                new SourceSpan(
                    $line->number,
                    1,
                    $endLine->number,
                    max(1, self::contentLength($endLine->content) + 1),
                ),
                ParserDiagnosticCode::UnclosedBlock->value,
                SourceExcerpt::forLine($line),
            ),
        );
    }

    private static function line(string $message, Line $line, ParserDiagnosticCode $code, ?\Throwable $previous = null): ParseException
    {
        return new ParseException(
            $message,
            $line->number,
            $line->content,
            $previous,
            ParserDiagnostic::forLine($message, $line, $code->value),
        );
    }

    private static function truncateSourceLine(string $line): string
    {
        return SourceExcerpt::fromSource(1, trim($line))->content;
    }

    private static function contentLength(string $content): int
    {
        if (!function_exists('mb_strlen')) {
            return strlen($content);
        }

        return mb_strlen($content, 'UTF-8');
    }
}
