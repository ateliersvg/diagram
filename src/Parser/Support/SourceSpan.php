<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;

/**
 * 1-based, end-exclusive source span.
 */
final readonly class SourceSpan
{
    public function __construct(
        public int $startLine,
        public int $startColumn,
        public int $endLine,
        public int $endColumn,
    ) {
        if ($startLine < 1 || $endLine < 1) {
            throw new InvalidArgumentException('Source span lines must be positive.');
        }

        if ($startColumn < 1 || $endColumn < 1) {
            throw new InvalidArgumentException('Source span columns must be positive.');
        }

        if ($endLine < $startLine || ($endLine === $startLine && $endColumn < $startColumn)) {
            throw new InvalidArgumentException('Source span end must not be before start.');
        }
    }

    public static function forLine(int $lineNumber, string $content): self
    {
        return new self($lineNumber, 1, $lineNumber, max(1, self::contentLength($content) + 1));
    }

    public static function fromLine(Line $line): self
    {
        return self::forLine($line->number, $line->content);
    }

    public static function fromSourceLine(SourceLine $line): self
    {
        return self::forLine($line->number, $line->raw);
    }

    public static function fromRange(SourceRange $range): self
    {
        return new self(
            $range->start->number,
            1,
            $range->end->number,
            max(1, self::contentLength($range->end->content) + 1),
        );
    }

    public function containsLine(int $lineNumber): bool
    {
        return $lineNumber >= $this->startLine && $lineNumber <= $this->endLine;
    }

    public function lineCount(): int
    {
        return $this->endLine - $this->startLine + 1;
    }

    public function isSingleLine(): bool
    {
        return $this->startLine === $this->endLine;
    }

    private static function contentLength(string $content): int
    {
        if (!function_exists('mb_strlen')) {
            return strlen($content);
        }

        return mb_strlen($content, 'UTF-8');
    }
}
