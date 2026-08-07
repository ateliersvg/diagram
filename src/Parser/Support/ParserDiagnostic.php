<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;

/**
 * Structured parser issue that callers can render independently from
 * exception text.
 */
final readonly class ParserDiagnostic
{
    public function __construct(
        public string $message,
        public SourceSpan $span,
        public string $severity = 'error',
        public ?string $code = null,
        public ?SourceExcerpt $source = null,
    ) {
    }

    public static function error(string $message, SourceSpan $span, ?string $code = null, ?SourceExcerpt $source = null): self
    {
        return new self($message, $span, 'error', $code, $source);
    }

    public static function forLine(string $message, Line $line, ?string $code = null): self
    {
        return self::error($message, SourceSpan::fromLine($line), $code, SourceExcerpt::forLine($line));
    }

    public static function forSourceLine(string $message, SourceLine $line, ?string $code = null): self
    {
        return self::error($message, SourceSpan::fromSourceLine($line), $code, SourceExcerpt::forSourceLine($line));
    }

    public function lineNumber(): int
    {
        return $this->span->startLine;
    }
}
