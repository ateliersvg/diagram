<?php

declare(strict_types=1);

namespace Atelier\Diagram\Exception;

use Atelier\Diagram\Parser\Support\ParserDiagnostic;
use Atelier\Diagram\Parser\Support\SourceSpan;

/**
 * Exception thrown when Mermaid source text cannot be parsed.
 *
 * Carries the 1-based line number and the raw source line so callers can
 * point users at the offending input.
 */
final class ParseException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $lineNumber,
        private readonly string $sourceLine = '',
        ?\Throwable $previous = null,
        private readonly ?ParserDiagnostic $diagnostic = null,
    ) {
        parent::__construct(\sprintf('%s at line %d: "%s"', $message, $lineNumber, $sourceLine), 0, $previous);
    }

    /**
     * Gets the 1-based line number of the offending source line.
     *
     * Named getLineNumber() because \Exception::getLine() is final.
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * Gets the raw source line that failed to parse.
     */
    public function getSourceLine(): string
    {
        return $this->sourceLine;
    }

    public function getDiagnostic(): ParserDiagnostic
    {
        return $this->diagnostic ?? ParserDiagnostic::error(
            $this->messageWithoutLocation(),
            SourceSpan::forLine($this->lineNumber, $this->sourceLine),
            source: \Atelier\Diagram\Parser\Support\SourceExcerpt::fromSource($this->lineNumber, $this->sourceLine),
        );
    }

    private function messageWithoutLocation(): string
    {
        $suffix = \sprintf(' at line %d: "%s"', $this->lineNumber, $this->sourceLine);
        $message = $this->getMessage();

        return str_ends_with($message, $suffix) ? substr($message, 0, -strlen($suffix)) : $message;
    }
}
