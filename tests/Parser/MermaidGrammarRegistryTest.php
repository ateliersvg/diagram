<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\MermaidDiagramParserInterface;
use Atelier\Diagram\Parser\MermaidGrammar;
use Atelier\Diagram\Parser\MermaidGrammarRegistry;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidGrammarRegistry::class)]
final class MermaidGrammarRegistryTest extends TestCase
{
    public function testDefaultHeadersAreUniqueAndComplete(): void
    {
        $headers = MermaidGrammarRegistry::default()->headers();

        $this->assertCount(\count(array_unique($headers)), $headers);
        $this->assertContains('stateDiagram-v2', $headers);
        $this->assertContains('stateDiagram', $headers);
        $this->assertContains('gitGraph', $headers);
        $this->assertContains('sequenceDiagram', $headers);
        $this->assertContains('flowchart', $headers);
        $this->assertContains('classDiagram', $headers);
        $this->assertContains('erDiagram', $headers);
        $this->assertContains('timeline', $headers);
        $this->assertContains('journey', $headers);
        $this->assertContains('mindmap', $headers);
        $this->assertContains('requirementDiagram', $headers);
        $this->assertContains('kanban', $headers);
        $this->assertContains('block', $headers);
        $this->assertContains('architecture', $headers);
    }

    public function testDefaultRegistryIsCached(): void
    {
        $this->assertSame(MermaidGrammarRegistry::default(), MermaidGrammarRegistry::default());
    }

    public function testDetectSkipsCommentsAndBlankLines(): void
    {
        $source = "%% generated\n\nsequenceDiagram\nA->>B: Hello";
        $match = MermaidGrammarRegistry::default()->detect($source);

        $this->assertSame('sequence', $match->grammar->name);
        $this->assertContains('sequenceDiagram', $match->grammar->headers);
        $this->assertSame(3, $match->header->line->number);
        $this->assertSame('sequenceDiagram', $match->header->line->content);
        $this->assertSame('A->>B: Hello', substr($source, $match->header->nextOffset));
    }

    public function testDetectedGrammarParsesThroughParserContract(): void
    {
        $match = MermaidGrammarRegistry::default()->detect("timeline\ntitle Launch\nsection Build\nPrivate beta : 2026-04\n");
        $diagram = $match->grammar->parseWithHeader("timeline\ntitle Launch\nsection Build\nPrivate beta : 2026-04\n", $match->header);

        $this->assertSame('Atelier\Diagram\Timeline\TimelineDiagram', $diagram::class);
    }

    public function testUnknownHeaderKeepsLineContext(): void
    {
        try {
            MermaidGrammarRegistry::default()->detect("pie\nA : 10");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame(1, $exception->getLineNumber());
            $this->assertSame('pie', $exception->getSourceLine());
        }
    }

    public function testExpectedHeadersAreHumanReadable(): void
    {
        $expected = MermaidGrammarRegistry::default()->expectedHeaders();

        $this->assertStringContainsString('"stateDiagram-v2"', $expected);
        $this->assertStringContainsString('"architecture"', $expected);
        $this->assertStringContainsString(' or ', $expected);
    }

    public function testGrammarsReturnsTheConstructedGrammars(): void
    {
        $grammars = MermaidGrammarRegistry::default()->grammars();

        $this->assertNotEmpty($grammars);
        $this->assertContainsOnlyInstancesOf(MermaidGrammar::class, $grammars);
        $names = array_map(static fn (MermaidGrammar $grammar): string => $grammar->name, $grammars);
        $this->assertContains('state', $names);
        $this->assertContains('architecture', $names);
    }

    public function testExpectedHeadersForSingleHeaderRegistryHasNoConjunction(): void
    {
        $registry = new MermaidGrammarRegistry([
            new MermaidGrammar('only', ['solo'], $this->stubParser()),
        ]);

        $this->assertSame('"solo"', $registry->expectedHeaders());
    }

    public function testDuplicateHeaderIsRejectedAtConstructionTime(): void
    {
        $parser = $this->stubParser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate Mermaid header "demo" in registry.');

        new MermaidGrammarRegistry([
            new MermaidGrammar('first', ['demo'], $parser),
            new MermaidGrammar('second', ['demo'], $parser),
        ]);
    }

    /**
     * @return MermaidDiagramParserInterface<DiagramModel>
     */
    private function stubParser(): MermaidDiagramParserInterface
    {
        return new class implements MermaidDiagramParserInterface {
            public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): DiagramModel
            {
                return new class implements DiagramModel {
                };
            }
        };
    }
}
