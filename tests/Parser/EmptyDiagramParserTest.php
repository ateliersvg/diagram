<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\ArchitectureDiagramParser;
use Atelier\Diagram\Parser\ClassDiagramParser;
use Atelier\Diagram\Parser\JourneyDiagramParser;
use Atelier\Diagram\Parser\KanbanDiagramParser;
use Atelier\Diagram\Parser\RequirementDiagramParser;
use Atelier\Diagram\Parser\TimelineDiagramParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassDiagramParser::class)]
#[CoversClass(TimelineDiagramParser::class)]
#[CoversClass(JourneyDiagramParser::class)]
#[CoversClass(RequirementDiagramParser::class)]
#[CoversClass(ArchitectureDiagramParser::class)]
#[CoversClass(KanbanDiagramParser::class)]
final class EmptyDiagramParserTest extends TestCase
{
    #[DataProvider('cases')]
    public function testEmptyDiagramThrowsStableDiagnostics(string $parserClass, string $source, string $lineContent, string $message, string $code): void
    {
        try {
            (new $parserClass())->parse($source);
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($code, $exception->getDiagnostic()->code);
            $this->assertSame($lineContent, $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    /**
     * @return iterable<string, array{class-string, string, string, string, string}>
     */
    public static function cases(): iterable
    {
        yield 'class' => [
            ClassDiagramParser::class,
            "classDiagram\n",
            'classDiagram',
            'Class diagram must contain at least one class at line 1: "classDiagram"',
            'parser.semantic_error',
        ];

        yield 'timeline' => [
            TimelineDiagramParser::class,
            "timeline\n",
            'timeline',
            'Timeline diagram must contain at least one section at line 1: "timeline"',
            'parser.semantic_error',
        ];

        yield 'journey' => [
            JourneyDiagramParser::class,
            "journey\n",
            'journey',
            'Journey diagram must contain at least one section at line 1: "journey"',
            'parser.semantic_error',
        ];

        yield 'requirement' => [
            RequirementDiagramParser::class,
            "requirementDiagram\n",
            'requirementDiagram',
            'Requirement diagram must contain at least one node at line 1: "requirementDiagram"',
            'parser.semantic_error',
        ];

        yield 'architecture' => [
            ArchitectureDiagramParser::class,
            "architecture\n",
            'architecture',
            'Architecture diagram must contain at least one node at line 1: "architecture"',
            'parser.semantic_error',
        ];

        yield 'kanban' => [
            KanbanDiagramParser::class,
            "kanban\n",
            'kanban',
            'Kanban diagram must contain at least one column at line 1: "kanban"',
            'parser.semantic_error',
        ];
    }
}
