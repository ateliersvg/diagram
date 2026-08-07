<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Journey\JourneyActor;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneyDiagramBuilder;
use Atelier\Diagram\Journey\JourneySection;
use Atelier\Diagram\Journey\JourneyTask;
use Atelier\Diagram\Parser\JourneyDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\JourneyDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JourneyDiagramParser::class)]
#[CoversClass(JourneyDiagramSerializer::class)]
#[CoversClass(JourneyDiagramBuilder::class)]
#[CoversClass(JourneyDiagram::class)]
#[CoversClass(JourneySection::class)]
#[CoversClass(JourneyTask::class)]
#[CoversClass(JourneyActor::class)]
final class JourneyDiagramParserTest extends TestCase
{
    public function testParsesJourneySubset(): void
    {
        $diagram = (new JourneyDiagramParser())->parse(<<<'MERMAID'
            journey
                title Checkout experience
                section Browse
                    Open product page: 5: Customer
                    Add to cart: 4: Customer
                section Payment
                    Enter card: 3: Customer, PSP
            MERMAID);

        $this->assertSame('Checkout experience', $diagram->title?->text);
        $this->assertSame('Browse', $diagram->sections[0]->title);
        $this->assertSame('Add to cart', $diagram->sections[0]->tasks[1]->text);
        $this->assertSame(4, $diagram->sections[0]->tasks[1]->score);
        $this->assertSame('PSP', $diagram->sections[1]->tasks[0]->actors[1]->name);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<< 'MERMAID'
            journey
                title Checkout experience
                section Browse
                    Open product page: 5: Customer
                section Payment
                    Enter card: 3: Customer, PSP
            MERMAID;

        $parser = new JourneyDiagramParser();
        $serializer = new JourneyDiagramSerializer();
        $canonical = $serializer->serialize($parser->parse($source));

        $this->assertSame($canonical, $serializer->serialize($parser->parse($canonical)));
    }

    public function testInvalidScoreThrowsAtSourceLine(): void
    {
        try {
            (new JourneyDiagramParser())->parse("journey\nsection Browse\nOpen product page: 6: Customer\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Journey task score must be between 1 and 5, got 6 at line 3: "Open product page: 6: Customer"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('Open product page: 6: Customer', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyActorItemThrowsAtSourceLine(): void
    {
        try {
            (new JourneyDiagramParser())->parse("journey\nsection Browse\nOpen product page: 5: Customer,\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Journey actors must not contain empty items at line 3: "Open product page: 5: Customer,"', $exception->getMessage());
            $this->assertSame('parser.empty_list_item', $exception->getDiagnostic()->code);
            $this->assertSame('Open product page: 5: Customer,', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyActorItemWithStableDiagnostic(): void
    {
        try {
            (new JourneyDiagramParser())->parse("journey\nsection Browse\nOpen product page: 5: Customer,\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Journey actors must not contain empty items at line 3: "Open product page: 5: Customer,"', $exception->getMessage());
            $this->assertSame('parser.empty_list_item', $exception->getDiagnostic()->code);
            $this->assertSame('Open product page: 5: Customer,', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptySectionThrowsAtSectionLine(): void
    {
        try {
            (new JourneyDiagramParser())->parse("journey\nsection Browse\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Journey section "Browse" must contain at least one task at line 2: "section Browse"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('section Browse', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testPreviousEmptySectionThrowsWhenNextSectionStarts(): void
    {
        try {
            (new JourneyDiagramParser())->parse("journey\nsection Browse\nsection Payment\nEnter card: 3: Customer\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Journey section "Browse" must contain at least one task at line 2: "section Browse"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('section Browse', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new JourneyDiagramParser())->parse("journey\nCustomer: Open product page\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported journey diagram syntax at line 2: "Customer: Open product page"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('Customer: Open product page', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesViaHeaderEntryPoint(): void
    {
        $source = "journey\ntitle Checkout experience\nsection Browse\nOpen product page: 5: Customer\nsection Payment\nEnter card: 3: Customer, PSP\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new JourneyDiagramParser())->parse($source, null, $header);

        $this->assertSame('Checkout experience', $diagram->title?->text);
        $this->assertSame('Browse', $diagram->sections[0]->title);
        $this->assertSame('PSP', $diagram->sections[1]->tasks[0]->actors[1]->name);
    }
}
