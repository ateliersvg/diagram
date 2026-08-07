<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;
use Atelier\Diagram\Parser\Support\ParserDiagnostic;
use Atelier\Diagram\Parser\Support\SourceExcerpt;
use Atelier\Diagram\Parser\Support\SourceRange;
use Atelier\Diagram\Parser\Support\SourceSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserDiagnostic::class)]
#[CoversClass(SourceExcerpt::class)]
#[CoversClass(SourceSpan::class)]
#[CoversClass(SourceRange::class)]
final class ParserDiagnosticTest extends TestCase
{
    public function testBuildsSpanFromTrimmedLine(): void
    {
        $span = SourceSpan::fromLine(new Line(7, 'A --> B'));

        $this->assertSame(7, $span->startLine);
        $this->assertSame(1, $span->startColumn);
        $this->assertSame(7, $span->endLine);
        $this->assertSame(8, $span->endColumn);
        $this->assertTrue($span->isSingleLine());
        $this->assertTrue($span->containsLine(7));
        $this->assertFalse($span->containsLine(8));
    }

    public function testBuildsSpanFromRawSourceLine(): void
    {
        $span = SourceSpan::fromSourceLine(new SourceLine(4, '    todo [Todo]'));

        $this->assertSame(4, $span->startLine);
        $this->assertSame(16, $span->endColumn);
    }

    public function testBuildsSpanFromMultibyteSourceLine(): void
    {
        $span = SourceSpan::fromLine(new Line(4, 'é😀A --> B'));

        $this->assertSame(4, $span->startLine);
        $this->assertSame(10, $span->endColumn);
    }

    public function testBuildsSpanFromRange(): void
    {
        $range = new SourceRange(new Line(3, 'subgraph A'), new Line(8, 'end'));
        $span = SourceSpan::fromRange($range);

        $this->assertSame(3, $span->startLine);
        $this->assertSame(8, $span->endLine);
        $this->assertSame(4, $span->endColumn);
        $this->assertSame(6, $span->lineCount());
        $this->assertFalse($span->isSingleLine());
    }

    public function testRejectsInvalidSpanCoordinates(): void
    {
        try {
            new SourceSpan(3, 4, 3, 2);
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Source span end must not be before start.', $exception->getMessage());
        }
    }

    public function testDiagnosticCarriesMessageSeverityCodeAndSpan(): void
    {
        $diagnostic = ParserDiagnostic::forLine('Unsupported flowchart syntax', new Line(9, 'bad'), 'parser.unsupported_syntax');

        $this->assertSame('Unsupported flowchart syntax', $diagnostic->message);
        $this->assertSame('error', $diagnostic->severity);
        $this->assertSame('parser.unsupported_syntax', $diagnostic->code);
        $this->assertSame(9, $diagnostic->lineNumber());
        $this->assertSame('bad', $diagnostic->source?->content);
        $this->assertSame(9, $diagnostic->source?->lineNumber);
    }

    public function testDiagnosticSourceExcerptIsShortAndStable(): void
    {
        $diagnostic = ParserDiagnostic::forLine('Long line', new Line(4, str_repeat('a', 200)), 'parser.line_too_long');

        $this->assertTrue($diagnostic->source?->truncated);
        $this->assertSame(120, strlen($diagnostic->source?->content ?? ''));
        $this->assertStringEndsWith('...', $diagnostic->source?->content ?? '');
    }

    public function testBuildsSourceExcerptFromRawSourceLine(): void
    {
        $diagnostic = ParserDiagnostic::forSourceLine('Bad indentation', new SourceLine(6, '    todo [Todo]'), 'parser.syntax_error');

        $this->assertSame('    todo [Todo]', $diagnostic->source?->content);
    }
}
