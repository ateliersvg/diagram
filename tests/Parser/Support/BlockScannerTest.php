<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\BlockFrame;
use Atelier\Diagram\Parser\Support\BlockScanner;
use Atelier\Diagram\Parser\Support\BlockSpan;
use Atelier\Diagram\Parser\Support\ParserDiagnostic;
use Atelier\Diagram\Parser\Support\SourceRange;
use Atelier\Diagram\Parser\Support\SourceSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockScanner::class)]
#[CoversClass(BlockFrame::class)]
#[CoversClass(BlockSpan::class)]
#[CoversClass(ParserDiagnostic::class)]
#[CoversClass(SourceRange::class)]
#[CoversClass(SourceSpan::class)]
final class BlockScannerTest extends TestCase
{
    public function testTracksNestedBlocksAndTouchedValues(): void
    {
        $scanner = new BlockScanner();
        $outer = $scanner->begin('subgraph', 'outer', 'Outer', new Line(2, 'subgraph outer [Outer]'));
        $inner = $scanner->begin('subgraph', 'inner', 'Inner', new Line(3, 'subgraph inner [Inner]'));

        $this->assertSame(2, $scanner->depth());

        $scanner->touchAll('A');

        $innerSpan = $scanner->end(new Line(5, 'end'), 'flowchart');
        $outerSpan = $scanner->end(new Line(6, 'end'), 'flowchart');

        $this->assertSame('outer', $outer->id);
        $this->assertSame('outer', $inner->parentId);
        $this->assertSame(1, $innerSpan->depth);
        $this->assertSame(['A'], $innerSpan->touchedValues);
        $this->assertSame(['A'], $outerSpan->touchedValues);
        $this->assertSame(2, $outerSpan->startLine->number);
        $this->assertSame(6, $outerSpan->endLine->number);
        $this->assertSame(5, $outerSpan->range()->lineCount());
        $this->assertTrue($outerSpan->range()->contains(4));
        $this->assertFalse($outerSpan->range()->contains(7));
    }

    public function testEndExpectedClosesMatchingKind(): void
    {
        $scanner = new BlockScanner();
        $scanner->begin('alt', 'alt-1', 'Auth', new Line(2, 'alt Auth'));

        $span = $scanner->endExpected(new Line(4, 'end'), 'sequence', 'alt');

        $this->assertSame('alt', $span->kind);
        $this->assertSame(0, $scanner->depth());
    }

    public function testAssertCurrentKindReturnsMatchingFrame(): void
    {
        $scanner = new BlockScanner();
        $opened = $scanner->begin('alt', 'alt-1', 'Auth', new Line(2, 'alt Auth'));

        $frame = $scanner->assertCurrentKind(new Line(3, 'else'), 'sequence', 'alt');

        $this->assertSame($opened, $frame);
        $this->assertSame(1, $scanner->depth());
    }

    public function testFramesExposesOpenStackInOrder(): void
    {
        $scanner = new BlockScanner();
        $scanner->begin('subgraph', 'outer', 'Outer', new Line(2, 'subgraph outer'));
        $scanner->begin('subgraph', 'inner', 'Inner', new Line(3, 'subgraph inner'));

        $frames = $scanner->frames();

        $this->assertCount(2, $frames);
        $this->assertSame('outer', $frames[0]->id);
        $this->assertSame('inner', $frames[1]->id);
    }

    public function testLastLineTracksMostRecentObservation(): void
    {
        $scanner = new BlockScanner();
        $this->assertNull($scanner->lastLine());

        $scanner->observe(new Line(5, 'A --> B'));

        $this->assertSame(5, $scanner->lastLine()?->number);
    }

    public function testUnexpectedEndThrowsAtEndLine(): void
    {
        try {
            (new BlockScanner())->end(new Line(4, 'end'), 'flowchart');
            $this->fail('Expected a parser exception.');
        } catch (ParseException $exception) {
            $this->assertSame('Unexpected flowchart block end at line 4: "end"', $exception->getMessage());
            $this->assertSame('parser.unexpected_block_end', $exception->getDiagnostic()->code);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEndExpectedRejectsMismatchedKindWithoutClosingCurrentFrame(): void
    {
        $scanner = new BlockScanner();
        $scanner->begin('loop', 'loop-1', 'Retry', new Line(2, 'loop Retry'));

        try {
            $scanner->endExpected(new Line(4, 'else'), 'sequence', 'alt');
            $this->fail('Expected a parser exception.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Expected sequence alt block, got loop block at line 4', $exception->getMessage());
        }

        $this->assertSame(1, $scanner->depth());
        $this->assertSame('loop', $scanner->current()?->kind);
    }

    public function testAssertCurrentKindRejectsMissingOpenFrame(): void
    {
        try {
            (new BlockScanner())->assertCurrentKind(new Line(4, 'else'), 'sequence', 'alt');
            $this->fail('Expected a parser exception.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected open sequence alt block at line 4: "else"', $exception->getMessage());
            $this->assertSame('parser.expected_open_block', $exception->getDiagnostic()->code);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testAssertCurrentKindRejectsMismatchedFrameKind(): void
    {
        $scanner = new BlockScanner();
        $scanner->begin('loop', 'loop-1', 'Retry', new Line(2, 'loop Retry'));

        try {
            $scanner->assertCurrentKind(new Line(4, 'else'), 'sequence', 'alt');
            $this->fail('Expected a parser exception.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected sequence alt block, got loop block at line 4: "else"', $exception->getMessage());
            $this->assertSame('parser.expected_block_kind', $exception->getDiagnostic()->code);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testAssertClosedThrowsAtOpeningLine(): void
    {
        $scanner = new BlockScanner();
        $scanner->begin('subgraph', 'payments', 'Payments', new Line(3, 'subgraph payments [Payments]'));
        $scanner->observe(new Line(5, 'A --> B'));

        try {
            $scanner->assertClosed('flowchart');
            $this->fail('Expected a parser exception.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed flowchart block at line 3: "subgraph payments [Payments]"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(5, $exception->getDiagnostic()->span->endLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->lineCount());
        }
    }
}
