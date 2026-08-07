<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Kanban\KanbanCard;
use Atelier\Diagram\Kanban\KanbanColumn;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
use Atelier\Diagram\Parser\KanbanDiagramParser;
use Atelier\Diagram\Renderer\Markdown\KanbanDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KanbanDiagramParser::class)]
#[CoversClass(KanbanDiagramSerializer::class)]
#[CoversClass(KanbanDiagram::class)]
#[CoversClass(KanbanDiagramBuilder::class)]
#[CoversClass(KanbanColumn::class)]
#[CoversClass(KanbanCard::class)]
final class KanbanDiagramParserTest extends TestCase
{
    public function testParsesKanbanSubset(): void
    {
        $diagram = (new KanbanDiagramParser())->parse(<<<'MERMAID'
            kanban
                title Delivery board
                todo [Todo]
                    REQ-1 [Write parser]
                    UI-2 [Review SVG output]
                doing [Doing]
                    LAY-3 [Layout docs]
            MERMAID);

        $this->assertSame('Delivery board', $diagram->title?->text);
        $this->assertSame('todo', $diagram->columns[0]->id);
        $this->assertSame('Todo', $diagram->columns[0]->label);
        $this->assertSame('UI-2', $diagram->columns[0]->cards[1]->id);
        $this->assertSame('LAY-3', $diagram->columns[1]->cards[0]->id);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            kanban
                title Delivery board
                todo [Todo]
                    REQ-1 [Write parser]
                done [Done]
                    CORE-1 [Scene renderer]
            MERMAID;

        $parser = new KanbanDiagramParser();
        $serializer = new KanbanDiagramSerializer();

        $mermaid = $serializer->serialize($parser->parse($source));

        $this->assertSame($mermaid, $serializer->serialize($parser->parse($mermaid)));
    }

    public function testRejectsTabs(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n\ttodo [Todo]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame("Kanban indentation must use spaces only at line 2: \"\ttodo [Todo]\"", $exception->getMessage());
            $this->assertSame('parser.indentation_tabs', $exception->getDiagnostic()->code);
            $this->assertSame("\ttodo [Todo]", $exception->getSourceLine());
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsBadIndentationWithRawSourceLine(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n  todo [Todo]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported kanban diagram syntax at line 2: "  todo [Todo]"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('  todo [Todo]', $exception->getSourceLine());
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsCardBeforeColumn(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n        REQ-1 [Write parser]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Kanban card must belong to a column at line 2: "        REQ-1 [Write parser]"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsInvalidIdentifierWithLineContext(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n    1todo [Todo]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported kanban diagram syntax at line 2: "    1todo [Todo]"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('    1todo [Todo]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsEmptyColumnLabelWithStableDiagnostic(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n    todo [   ]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Kanban column label must not be empty at line 2: "    todo [   ]"', $exception->getMessage());
            $this->assertSame('parser.empty_value', $exception->getDiagnostic()->code);
            $this->assertSame('    todo [   ]', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testRejectsDuplicateColumnWithStableDiagnostic(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n    todo [Todo]\n    todo [Todo again]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate kanban column id "todo" at line 3: "    todo [Todo again]"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('    todo [Todo again]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateCardWithStableDiagnostic(): void
    {
        try {
            (new KanbanDiagramParser())->parse("kanban\n    todo [Todo]\n        REQ-1 [Write parser]\n        REQ-1 [Write parser again]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate kanban card id "REQ-1" at line 4: "        REQ-1 [Write parser again]"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('        REQ-1 [Write parser again]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }
}
