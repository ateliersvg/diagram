<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;
use Atelier\Diagram\Parser\SourceLineStream;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceLineStream::class)]
#[CoversClass(SourceLine::class)]
#[CoversClass(Line::class)]
#[CoversClass(ParserInputLimits::class)]
#[CoversClass(ParseErrors::class)]
final class SourceLineStreamTest extends TestCase
{
    public function testYieldsRawAndTrimmedLinesWithIndentation(): void
    {
        $stream = new SourceLineStream("kanban\n    todo [Todo]\n        REQ-1 [Write parser]");

        $lines = $stream->toArray();

        $this->assertCount(3, $lines);
        $this->assertSame(1, $lines[0]->number);
        $this->assertSame('kanban', $lines[0]->raw);
        $this->assertSame('kanban', $lines[0]->trimmed);
        $this->assertSame(0, $lines[0]->indent);
        $this->assertSame(4, $lines[1]->indent);
        $this->assertSame('    todo [Todo]', $lines[1]->raw);
        $this->assertSame('todo [Todo]', $lines[1]->trimmed);
        $this->assertSame(8, $lines[2]->indent);
    }

    public function testSkipsBlankAndCommentLinesWithoutLosingNumbers(): void
    {
        $stream = new SourceLineStream("%% comment\n\nkanban\n  %% inner\n    todo [Todo]\n");

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(3, $lines[0]->number);
        $this->assertSame(5, $lines[1]->number);
    }

    public function testDetectsTabsAndConvertsToLineValues(): void
    {
        $line = new SourceLine(2, "\titem");

        $this->assertTrue($line->containsTab());
        $this->assertSame(0, $line->indent);
        $this->assertSame('item', $line->asLine()->content);
        $this->assertSame("\titem", $line->asRawLine()->content);
    }

    public function testHandlesCarriageReturnLineFeedEndings(): void
    {
        $stream = new SourceLineStream("one\r\n  two\r\n    three");

        $this->assertSame(['one', 'two', 'three'], array_map(static fn (SourceLine $line): string => $line->trimmed, $stream->toArray()));
    }

    public function testHandlesCarriageReturnOnlyEndings(): void
    {
        $stream = new SourceLineStream("one\r  two\r    three");

        $this->assertSame(['one', 'two', 'three'], array_map(static fn (SourceLine $line): string => $line->trimmed, $stream->toArray()));
    }

    public function testHandlesVerticalTabAndFormFeedEndings(): void
    {
        $stream = new SourceLineStream("one\v  two\f    three");

        $this->assertSame(['one', 'two', 'three'], array_map(static fn (SourceLine $line): string => $line->trimmed, $stream->toArray()));
    }

    public function testHandlesUnicodeLineSeparators(): void
    {
        $stream = new SourceLineStream("one\u{2028}  two");

        $this->assertSame(['one', 'two'], array_map(static fn (SourceLine $line): string => $line->trimmed, $stream->toArray()));
    }

    public function testHandlesTrailingNewline(): void
    {
        $stream = new SourceLineStream("one\n  two\n");

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(['one', 'two'], array_map(static fn (SourceLine $line): string => $line->trimmed, $lines));
    }

    public function testEmptySourceYieldsNothing(): void
    {
        $this->assertSame([], (new SourceLineStream(''))->toArray());
    }

    public function testCanStartAfterAnEarlierOffset(): void
    {
        $stream = new SourceLineStream("header\n    todo [Todo]\n        REQ-1 [Write parser]", null, 7, 2);

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(2, $lines[0]->number);
        $this->assertSame('    todo [Todo]', $lines[0]->raw);
        $this->assertSame(3, $lines[1]->number);
        $this->assertSame('        REQ-1 [Write parser]', $lines[1]->raw);
    }

    public function testRejectsOversizedSource(): void
    {
        $stream = new SourceLineStream('abcdef', new ParserInputLimits(maxSourceBytes: 5));

        try {
            $stream->toArray();
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Parser source exceeds maximum size of 5 bytes, got 6 bytes at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.source_too_large', $exception->getDiagnostic()->code);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsOversizedRawLine(): void
    {
        $stream = new SourceLineStream("kanban\n    abcdef", new ParserInputLimits(maxLineBytes: 9));

        try {
            $stream->toArray();
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Parser source line exceeds maximum length of 9 bytes at line 2: "abcdef"', $exception->getMessage());
            $this->assertSame(2, $exception->getLineNumber());
            $this->assertSame('parser.line_too_long', $exception->getDiagnostic()->code);
            $this->assertSame('abcdef', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }
}
