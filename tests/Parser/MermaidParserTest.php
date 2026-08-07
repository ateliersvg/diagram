<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParseResult;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Timeline\TimelineDiagram;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Dispatch only: header detection and the resulting model type. Grammar
 * details are covered by the per-grammar parser tests.
 */
#[CoversClass(MermaidParser::class)]
#[CoversClass(ParserInputLimits::class)]
#[CoversClass(ParseResult::class)]
final class MermaidParserTest extends TestCase
{
    public function testDispatchesStateDiagramHeaderToStateDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("stateDiagram-v2\n[*] --> Idle");

        $this->assertInstanceOf(DiagramModel::class, $diagram);
        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesIndentedStateDiagramHeader(): void
    {
        $diagram = (new MermaidParser())->parse("  stateDiagram-v2\n  [*] --> Idle");

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesBomPrefixedStateDiagramHeader(): void
    {
        $diagram = (new MermaidParser())->parse("\xEF\xBB\xBFstateDiagram-v2\n[*] --> Idle");

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesCarriageReturnOnlyStateDiagramHeader(): void
    {
        $diagram = (new MermaidParser())->parse("stateDiagram-v2\r[*] --> Idle\r");

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesVerticalTabSeparatedStateDiagramHeader(): void
    {
        $diagram = (new MermaidParser())->parse("stateDiagram-v2\v[*] --> Idle\v");

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesFormFeedSeparatedStateDiagramHeader(): void
    {
        $diagram = (new MermaidParser())->parse("stateDiagram-v2\f[*] --> Idle\f");

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesLegacyStateDiagramHeader(): void
    {
        $diagram = (new MermaidParser())->parse("%% comment\n\nstateDiagram\nIdle --> Done");

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testDispatchesGitGraphHeaderToGitGraphParser(): void
    {
        $graph = (new MermaidParser())->parse("gitGraph LR:\ncommit");

        $this->assertInstanceOf(GitGraph::class, $graph);
    }

    public function testDispatchesSequenceDiagramHeaderToSequenceDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("sequenceDiagram\nA->>B: Hello");

        $this->assertInstanceOf(SequenceDiagram::class, $diagram);
    }

    public function testDispatchesFlowchartHeaderToFlowchartParser(): void
    {
        $diagram = (new MermaidParser())->parse("flowchart TD\nA --> B");

        $this->assertInstanceOf(Flowchart::class, $diagram);
    }

    public function testDispatchesClassDiagramHeaderToClassDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("classDiagram\nclass User");

        $this->assertInstanceOf(ClassDiagram::class, $diagram);
    }

    public function testDispatchesErDiagramHeaderToErDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("erDiagram\nCUSTOMER {\nstring email\n}");

        $this->assertInstanceOf(ErDiagram::class, $diagram);
    }

    public function testDispatchesTimelineHeaderToTimelineDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("timeline\nsection Discovery\nResearch complete : 2026-01");

        $this->assertInstanceOf(TimelineDiagram::class, $diagram);
    }

    public function testDispatchesJourneyHeaderToJourneyDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("journey\nsection Browse\nOpen product page: 5: Customer");

        $this->assertInstanceOf(JourneyDiagram::class, $diagram);
    }

    public function testDispatchesMindmapHeaderToMindmapDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("mindmap\n  root((Atelier))\n    Layout\n");

        $this->assertInstanceOf(MindmapDiagram::class, $diagram);
    }

    public function testDispatchesRequirementDiagramHeaderToRequirementDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("requirementDiagram\nrequirement checkout {\nid: REQ-1\n}\n");

        $this->assertInstanceOf(RequirementDiagram::class, $diagram);
    }

    public function testDispatchesKanbanHeaderToKanbanDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("kanban\n    todo [Todo]\n        REQ-1 [Write parser]\n");

        $this->assertInstanceOf(KanbanDiagram::class, $diagram);
    }

    public function testDispatchesBlockHeaderToBlockDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("block\nblock Solver [LayoutSolver]\n");

        $this->assertInstanceOf(BlockDiagram::class, $diagram);
    }

    public function testDispatchesArchitectureHeaderToArchitectureDiagramParser(): void
    {
        $diagram = (new MermaidParser())->parse("architecture\ncomponent App [Frontend app]\n");

        $this->assertInstanceOf(ArchitectureDiagram::class, $diagram);
    }

    public function testDispatchesC4HeadersToC4DiagramParser(): void
    {
        foreach (['C4Context', 'C4Container', 'C4Component'] as $header) {
            $diagram = (new MermaidParser())->parse($header."\nPerson(user, \"User\")\n");

            $this->assertInstanceOf(C4Diagram::class, $diagram);
        }
    }

    public function testUnknownHeaderThrowsAtLineOne(): void
    {
        try {
            (new MermaidParser())->parse("pie\nA : 10");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unknown diagram header, expected "stateDiagram-v2", "stateDiagram", "gitGraph", "sequenceDiagram", "flowchart", "classDiagram", "erDiagram", "timeline", "journey", "mindmap", "requirementDiagram", "kanban", "block", "architecture", "C4Context", "C4Container" or "C4Component" at line 1: "pie"', $exception->getMessage());
            $this->assertSame('parser.unknown_header', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getLineNumber());
            $this->assertSame('pie', $exception->getSourceLine());
            $this->assertSame('pie', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyInputThrowsAtLineOne(): void
    {
        try {
            (new MermaidParser())->parse('');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.empty_input', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getLineNumber());
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testCommentOnlyInputThrowsAtLineOne(): void
    {
        try {
            (new MermaidParser())->parse("%% only comments\n\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.empty_input', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getLineNumber());
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testBomPrefixedCommentOnlyInputThrowsAtLineOne(): void
    {
        try {
            (new MermaidParser())->parse("\xEF\xBB\xBF%% only comments\n\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.empty_input', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getLineNumber());
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testConstructorInputLimitsRejectOversizedSourceBeforeDispatch(): void
    {
        $parser = new MermaidParser(limits: new ParserInputLimits(maxSourceBytes: 5));

        try {
            $parser->parse("stateDiagram-v2\n[*] --> Idle");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Parser source exceeds maximum size of 5 bytes, got 28 bytes at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.source_too_large', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getLineNumber());
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testPerCallInputLimitsPropagateToGrammarParser(): void
    {
        $parser = new MermaidParser();

        try {
            $parser->parse("stateDiagram-v2\nIdle --> VeryLongStateName", new ParserInputLimits(maxLineBytes: 20));
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Parser source line exceeds maximum length of 20 bytes at line 2: "Idle --> VeryLongStateName"', $exception->getMessage());
            $this->assertSame('parser.line_too_long', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getLineNumber());
            $this->assertSame('Idle --> VeryLongStateName', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testPerCallInputLimitsOverrideConstructorLimits(): void
    {
        $parser = new MermaidParser(limits: new ParserInputLimits(maxSourceBytes: 5));

        $diagram = $parser->parse("stateDiagram-v2\n[*] --> Idle", new ParserInputLimits(maxSourceBytes: 100));

        $this->assertInstanceOf(StateDiagram::class, $diagram);
    }

    public function testTryParseReturnsSuccessResult(): void
    {
        $result = (new MermaidParser())->tryParse("stateDiagram-v2\n[*] --> Idle");

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(StateDiagram::class, $result->getModel());
        $this->assertNull($result->getDiagnostic());
    }

    public function testTryParseReturnsFailureResultWithoutThrowing(): void
    {
        $result = (new MermaidParser())->tryParse("pie\nA : 10");

        $this->assertTrue($result->isFailure());
        $this->assertNull($result->getModel());
        $this->assertSame('Unknown diagram header, expected "stateDiagram-v2", "stateDiagram", "gitGraph", "sequenceDiagram", "flowchart", "classDiagram", "erDiagram", "timeline", "journey", "mindmap", "requirementDiagram", "kanban", "block", "architecture", "C4Context", "C4Container" or "C4Component" at line 1: "pie"', $result->getException()?->getMessage());
        $this->assertSame('parser.unknown_header', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryParseRejectsWhitespaceOnlyInputAsEmptyInput(): void
    {
        $result = (new MermaidParser())->tryParse("\n \t \n");

        $this->assertTrue($result->isFailure());
        $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $result->getException()?->getMessage());
        $this->assertSame('parser.empty_input', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryParseRejectsCommentOnlyInputAsEmptyInput(): void
    {
        $result = (new MermaidParser())->tryParse("%% comment only\n\n");

        $this->assertTrue($result->isFailure());
        $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $result->getException()?->getMessage());
        $this->assertSame('parser.empty_input', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryParseUsesInputLimits(): void
    {
        $result = (new MermaidParser(limits: new ParserInputLimits(maxSourceBytes: 5)))->tryParse("stateDiagram-v2\n[*] --> Idle");

        $this->assertTrue($result->isFailure());
        $this->assertSame('Parser source exceeds maximum size of 5 bytes, got 28 bytes at line 1: ""', $result->getException()?->getMessage());
        $this->assertSame('parser.source_too_large', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }
}
