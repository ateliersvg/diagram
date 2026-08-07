<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\SequenceDiagramParser;
use Atelier\Diagram\Sequence\MessageArrow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceDiagramParser::class)]
final class SequenceDiagramParserTest extends TestCase
{
    public function testParsesParticipantsTitleAndMessages(): void
    {
        $source = <<<'MERMAID'
            sequenceDiagram
                title Checkout
                participant User as Customer
                participant Api as API
                loop retry
                User->>Api: Pay
                Api-->>User: Receipt
                end
                alt fallback
                Api->>Api: Validate
                else manual
                User->>Api: Manual review
                end
                opt notify
                Api-->>User: Email
                end
                par audit
                Api->>Api: Store audit
                and metrics
                Api->>Api: Store metrics
                end
                activate Api
                Api-->>User: Done
                deactivate Api
            MERMAID;

        $diagram = (new SequenceDiagramParser())->parse($source);

        $this->assertSame('Checkout', $diagram->title?->text);
        $this->assertSame('User', $diagram->participants[0]->id);
        $this->assertSame('Customer', $diagram->participants[0]->label);
        $this->assertSame('Receipt', $diagram->messages[1]->label);
        $this->assertSame(MessageArrow::Dashed, $diagram->messages[1]->arrow);
        $this->assertSame('retry', $diagram->blocks[0]->label);
        $this->assertSame(0, $diagram->blocks[0]->firstMessageIndex);
        $this->assertSame(1, $diagram->blocks[0]->lastMessageIndex);
        $this->assertSame('fallback', $diagram->blocks[1]->label);
        $this->assertSame('manual', $diagram->blocks[1]->branches[1]->label);
        $this->assertSame('notify', $diagram->blocks[2]->label);
        $this->assertSame('audit', $diagram->blocks[3]->branches[0]->label);
        $this->assertSame('metrics', $diagram->blocks[3]->branches[1]->label);
        $this->assertSame('Api', $diagram->activations[0]->participant);
    }

    public function testAutoDeclaresMessageParticipants(): void
    {
        $diagram = (new SequenceDiagramParser())->parse("sequenceDiagram\nA->>B: Ping\n");

        $this->assertSame(['A', 'B'], array_map(static fn ($participant): string => $participant->id, $diagram->participants));
    }

    public function testNestedBlockThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nloop retry\nalt fallback\nA->>B: Ping\nend\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Nested sequence blocks are not supported at line 3: "alt fallback"', $exception->getMessage());
            $this->assertSame('parser.nested_block', $exception->getDiagnostic()->code);
            $this->assertSame('alt fallback', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyDiagramWithoutParticipantsThrowsAtHeaderLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence diagram must contain at least one participant at line 1: "sequenceDiagram"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('sequenceDiagram', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyMessageLabelThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nA->>B:   \n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence message label must not be empty at line 2: "A->>B:"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('A->>B:', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testActivateBeforeFirstMessageThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nactivate Api\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence activation must follow a message at line 2: "activate Api"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('activate Api', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testDeactivateBeforeActivateThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nparticipant Api\ndeactivate Api\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence participant "Api" is not active at line 3: "deactivate Api"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('deactivate Api', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nnote right of A: todo\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported sequence diagram syntax at line 2: "note right of A: todo"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('note right of A: todo', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnexpectedEndThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unexpected sequence block end at line 2: "end"', $exception->getMessage());
            $this->assertSame('parser.unexpected_block_end', $exception->getDiagnostic()->code);
            $this->assertSame('end', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnclosedBlockThrowsAtOpeningLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nloop retry\nA->>B: Ping\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame(2, $exception->getLineNumber());
            $this->assertSame('loop retry', $exception->getSourceLine());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testElseOutsideAltThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nelse fallback\nA->>B: Ping\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unexpected sequence "else" at line 2: "else fallback"', $exception->getMessage());
            $this->assertSame('parser.unexpected_branch', $exception->getDiagnostic()->code);
            $this->assertSame('else fallback', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testElseInsideLoopThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nloop retry\nA->>B: Ping\nelse fallback\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence "else" is only supported inside alt blocks at line 4: "else fallback"', $exception->getMessage());
            $this->assertSame('parser.branch_context', $exception->getDiagnostic()->code);
            $this->assertSame('else fallback', $exception->getDiagnostic()->source?->content);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testAndInsideAltThrowsAtSourceLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nalt fallback\nA->>B: Ping\nand metrics\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence "and" is only supported inside par blocks at line 4: "and metrics"', $exception->getMessage());
            $this->assertSame('parser.branch_context', $exception->getDiagnostic()->code);
            $this->assertSame('and metrics', $exception->getDiagnostic()->source?->content);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyBlockThrowsAtOpeningLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nloop retry\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence block must contain at least one message at line 2: "loop retry"', $exception->getMessage());
            $this->assertSame('parser.empty_block', $exception->getDiagnostic()->code);
            $this->assertSame('loop retry', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyBranchThrowsAtOpeningLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nalt fallback\nelse manual\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence branch must contain at least one message at line 2: "alt fallback"', $exception->getMessage());
            $this->assertSame('parser.empty_branch', $exception->getDiagnostic()->code);
            $this->assertSame('alt fallback', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyFinalBranchThrowsAtBranchLine(): void
    {
        try {
            (new SequenceDiagramParser())->parse("sequenceDiagram\nalt fallback\nA->>B: Ping\nelse manual\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Sequence branch must contain at least one message at line 4: "else manual"', $exception->getMessage());
            $this->assertSame('parser.empty_branch', $exception->getDiagnostic()->code);
            $this->assertSame('else manual', $exception->getDiagnostic()->source?->content);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }
}
