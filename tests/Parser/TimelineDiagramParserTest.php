<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\TimelineDiagramParser;
use Atelier\Diagram\Renderer\Markdown\TimelineDiagramSerializer;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;
use Atelier\Diagram\Timeline\TimelineEvent;
use Atelier\Diagram\Timeline\TimelineSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimelineDiagramParser::class)]
#[CoversClass(TimelineDiagramSerializer::class)]
#[CoversClass(TimelineDiagram::class)]
#[CoversClass(TimelineDiagramBuilder::class)]
#[CoversClass(TimelineSection::class)]
#[CoversClass(TimelineEvent::class)]
final class TimelineDiagramParserTest extends TestCase
{
    public function testParsesTimelineSubset(): void
    {
        $diagram = (new TimelineDiagramParser())->parse(<<<'MERMAID'
            timeline
                title Product launch
                section Discovery
                    Research complete : 2026-01
                    Prototype review : 2026-02
                section Build
                    Private beta : 2026-04
            MERMAID);

        $this->assertSame('Product launch', $diagram->title?->text);
        $this->assertSame('Discovery', $diagram->sections[0]->title);
        $this->assertSame('Prototype review', $diagram->sections[0]->events[1]->label);
        $this->assertSame('2026-04', $diagram->sections[1]->events[0]->date);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            timeline
                title Product launch
                section Discovery
                    Research complete : 2026-01
                section Build
                    Public launch : 2026-06
            MERMAID;

        $parser = new TimelineDiagramParser();
        $serializer = new TimelineDiagramSerializer();

        $this->assertSame($serializer->serialize($parser->parse($source)), $serializer->serialize($parser->parse($serializer->serialize($parser->parse($source)))));
    }

    public function testEventBeforeSectionThrowsAtSourceLine(): void
    {
        try {
            (new TimelineDiagramParser())->parse("timeline\nResearch complete : 2026-01\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Timeline event must belong to a section at line 2: "Research complete : 2026-01"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptySectionThrowsAtSectionLine(): void
    {
        try {
            (new TimelineDiagramParser())->parse("timeline\nsection Discovery\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Timeline section "Discovery" must contain at least one event at line 2: "section Discovery"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('section Discovery', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testPreviousEmptySectionThrowsWhenNextSectionStarts(): void
    {
        try {
            (new TimelineDiagramParser())->parse("timeline\nsection Discovery\nsection Build\nBeta : 2026-04\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Timeline section "Discovery" must contain at least one event at line 2: "section Discovery"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('section Discovery', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new TimelineDiagramParser())->parse("timeline\nphase Discovery\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported timeline diagram syntax at line 2: "phase Discovery"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('phase Discovery', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesViaHeaderEntryPoint(): void
    {
        $source = "timeline\ntitle Product launch\nsection Discovery\nResearch complete : 2026-01\nsection Build\nPublic launch : 2026-06\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new TimelineDiagramParser())->parse($source, null, $header);

        $this->assertSame('Product launch', $diagram->title?->text);
        $this->assertSame('Discovery', $diagram->sections[0]->title);
        $this->assertSame('2026-06', $diagram->sections[1]->events[0]->date);
    }
}
