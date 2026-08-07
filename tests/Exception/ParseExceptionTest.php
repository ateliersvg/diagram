<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Exception;

use Atelier\Diagram\Exception\ExceptionInterface;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Parser\Support\ParserDiagnostic;
use Atelier\Diagram\Parser\Support\SourceExcerpt;
use Atelier\Diagram\Parser\Support\SourceSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParseException::class)]
#[CoversClass(ParserDiagnostic::class)]
#[CoversClass(SourceExcerpt::class)]
#[CoversClass(SourceSpan::class)]
final class ParseExceptionTest extends TestCase
{
    public function testMessageContainsReasonLineNumberAndSourceLine(): void
    {
        $exception = new ParseException('Unknown keyword "foo"', 7, 'foo A --> B');

        $this->assertSame('Unknown keyword "foo" at line 7: "foo A --> B"', $exception->getMessage());
    }

    public function testExposesLineNumberAndSourceLine(): void
    {
        $exception = new ParseException('Unexpected token', 3, '[*] --> --> A');

        $this->assertSame(3, $exception->getLineNumber());
        $this->assertSame('[*] --> --> A', $exception->getSourceLine());
    }

    public function testSourceLineDefaultsToEmptyString(): void
    {
        $exception = new ParseException('Unexpected end of input', 12);

        $this->assertSame('', $exception->getSourceLine());
        $this->assertSame('Unexpected end of input at line 12: ""', $exception->getMessage());
    }

    public function testExposesDefaultDiagnosticFromLineContext(): void
    {
        $exception = new ParseException('Unsupported syntax', 5, 'bad line');
        $diagnostic = $exception->getDiagnostic();

        $this->assertSame('Unsupported syntax', $diagnostic->message);
        $this->assertSame('error', $diagnostic->severity);
        $this->assertNull($diagnostic->code);
        $this->assertSame(5, $diagnostic->span->startLine);
        $this->assertSame(9, $diagnostic->span->endColumn);
        $this->assertSame('bad line', $diagnostic->source?->content);
    }

    public function testExposesDefaultDiagnosticFromMultibyteLineContext(): void
    {
        $exception = new ParseException('Unsupported syntax', 5, 'é😀A --> B');
        $diagnostic = $exception->getDiagnostic();

        $this->assertSame(5, $diagnostic->span->startLine);
        $this->assertSame(10, $diagnostic->span->endColumn);
        $this->assertSame('é😀A --> B', $diagnostic->source?->content);
    }

    public function testExposesProvidedDiagnostic(): void
    {
        $diagnostic = ParserDiagnostic::error('Expected demo header', SourceSpan::forLine(2, 'other'), 'parser.expected_header');
        $exception = new ParseException('Expected demo header', 2, 'other', diagnostic: $diagnostic);

        $this->assertSame($diagnostic, $exception->getDiagnostic());
    }

    public function testIsALibraryRuntimeException(): void
    {
        $exception = new ParseException('Bad input', 1, 'x');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
    }
}
