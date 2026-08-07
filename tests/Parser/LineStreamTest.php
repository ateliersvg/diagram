<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\LineStream;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineStream::class)]
#[CoversClass(Line::class)]
#[CoversClass(ParserInputLimits::class)]
#[CoversClass(ParseErrors::class)]
final class LineStreamTest extends TestCase
{
    public function testYieldsTrimmedLinesWithOriginalLineNumbers(): void
    {
        $stream = new LineStream("stateDiagram-v2\n    [*] --> Idle\n  Idle --> Done");

        $lines = $stream->toArray();

        $this->assertCount(3, $lines);
        $this->assertSame(1, $lines[0]->number);
        $this->assertSame('stateDiagram-v2', $lines[0]->content);
        $this->assertSame(2, $lines[1]->number);
        $this->assertSame('[*] --> Idle', $lines[1]->content);
        $this->assertSame(3, $lines[2]->number);
        $this->assertSame('Idle --> Done', $lines[2]->content);
    }

    public function testSkipsBlankLinesWithoutLosingLineNumbers(): void
    {
        $stream = new LineStream("first\n\n   \nfourth");

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(1, $lines[0]->number);
        $this->assertSame(4, $lines[1]->number);
        $this->assertSame('fourth', $lines[1]->content);
    }

    public function testSkipsCommentLines(): void
    {
        $stream = new LineStream("%% header comment\ngitGraph\n  %% indented comment\n  commit");

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame('gitGraph', $lines[0]->content);
        $this->assertSame(4, $lines[1]->number);
        $this->assertSame('commit', $lines[1]->content);
    }

    public function testHandlesCarriageReturnLineFeedEndings(): void
    {
        $stream = new LineStream("one\r\ntwo\r\nthree");

        $lines = $stream->toArray();

        $this->assertCount(3, $lines);
        $this->assertSame(['one', 'two', 'three'], array_map(static fn (Line $l): string => $l->content, $lines));
    }

    public function testHandlesCarriageReturnOnlyEndings(): void
    {
        $stream = new LineStream("one\rtwo\rthree");

        $lines = $stream->toArray();

        $this->assertCount(3, $lines);
        $this->assertSame(['one', 'two', 'three'], array_map(static fn (Line $l): string => $l->content, $lines));
    }

    public function testHandlesVerticalTabAndFormFeedEndings(): void
    {
        $stream = new LineStream("one\vtwo\fthree");

        $lines = $stream->toArray();

        $this->assertCount(3, $lines);
        $this->assertSame(['one', 'two', 'three'], array_map(static fn (Line $l): string => $l->content, $lines));
    }

    public function testHandlesUnicodeLineSeparators(): void
    {
        $stream = new LineStream("one\u{2028}two");

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(['one', 'two'], array_map(static fn (Line $l): string => $l->content, $lines));
    }

    public function testHandlesTrailingNewline(): void
    {
        $stream = new LineStream("one\ntwo\n");

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(['one', 'two'], array_map(static fn (Line $l): string => $l->content, $lines));
    }

    public function testEmptySourceYieldsNothing(): void
    {
        $stream = new LineStream('');

        $this->assertSame([], $stream->toArray());
    }

    public function testIsIterable(): void
    {
        $stream = new LineStream("a\nb");

        $contents = [];
        foreach ($stream as $line) {
            $contents[] = $line->content;
        }

        $this->assertSame(['a', 'b'], $contents);
    }

    public function testCanStartAfterAnEarlierOffset(): void
    {
        $stream = new LineStream("header\nline1\nline2", null, 7, 2);

        $lines = $stream->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(2, $lines[0]->number);
        $this->assertSame('line1', $lines[0]->content);
        $this->assertSame(3, $lines[1]->number);
        $this->assertSame('line2', $lines[1]->content);
    }

    public function testRejectsOversizedSource(): void
    {
        $stream = new LineStream('abcdef', new ParserInputLimits(maxSourceBytes: 5));

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

    public function testRejectsOversizedLine(): void
    {
        $stream = new LineStream("ok\nabcdef", new ParserInputLimits(maxLineBytes: 5));

        try {
            $stream->toArray();
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Parser source line exceeds maximum length of 5 bytes at line 2: "abcdef"', $exception->getMessage());
            $this->assertSame(2, $exception->getLineNumber());
            $this->assertSame('parser.line_too_long', $exception->getDiagnostic()->code);
            $this->assertSame('abcdef', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsOversizedMultibyteLineWithUtf8Diagnostic(): void
    {
        $stream = new LineStream("ok\né😀é😀é😀", new ParserInputLimits(maxLineBytes: 6));

        try {
            $stream->toArray();
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Parser source line exceeds maximum length of 6 bytes at line 2: "é😀é😀é😀"', $exception->getMessage());
            $this->assertSame(2, $exception->getLineNumber());
            $this->assertSame('parser.line_too_long', $exception->getDiagnostic()->code);
            $this->assertSame('é😀é😀é😀', $exception->getDiagnostic()->source?->content);
            $this->assertTrue(mb_check_encoding($exception->getDiagnostic()->source?->content ?? '', 'UTF-8'));
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
            $this->assertSame(7, $exception->getDiagnostic()->span->endColumn);
        }
    }
}
