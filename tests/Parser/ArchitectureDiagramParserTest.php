<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Architecture\ArchitectureDiagramBuilder;
use Atelier\Diagram\Architecture\ArchitectureGroup;
use Atelier\Diagram\Architecture\ArchitectureNode;
use Atelier\Diagram\Architecture\ArchitectureNodeKind;
use Atelier\Diagram\Architecture\ArchitectureRelationship;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\ArchitectureDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\ArchitectureDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchitectureDiagramParser::class)]
#[CoversClass(ArchitectureDiagramSerializer::class)]
#[CoversClass(ArchitectureDiagramBuilder::class)]
#[CoversClass(ArchitectureDiagram::class)]
#[CoversClass(ArchitectureGroup::class)]
#[CoversClass(ArchitectureNode::class)]
#[CoversClass(ArchitectureRelationship::class)]
#[CoversClass(ArchitectureNodeKind::class)]
final class ArchitectureDiagramParserTest extends TestCase
{
    public function testParsesArchitectureSubset(): void
    {
        $diagram = (new ArchitectureDiagramParser())->parse(<<<'MERMAID'
            architecture
                title Checkout platform
                group Web [Web tier]
                    component App [Frontend app]
                    component Api [Checkout API]
                group Data [Data tier]
                    database Orders [Orders DB]
                App -> Api : calls
                Api -> Orders : writes
            MERMAID);

        $this->assertSame('Checkout platform', $diagram->title?->text);
        $this->assertSame('Web tier', $diagram->groups[0]->label->text);
        $this->assertSame('App', $diagram->nodes[0]->id);
        $this->assertSame('Web', $diagram->nodes[1]->groupId);
        $this->assertSame(ArchitectureNodeKind::Database, $diagram->nodes[2]->kind);
        $this->assertSame('writes', $diagram->relationships[1]->label?->text);
    }

    public function testParsesWithPreMatchedHeader(): void
    {
        $source = <<<'MERMAID'
            architecture
                group Web [Web tier]
                    component App [Frontend app]
                App -> App : self
            MERMAID;
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new ArchitectureDiagramParser())->parse($source, null, $header);

        $this->assertSame('Web tier', $diagram->groups[0]->label->text);
        $this->assertSame('App', $diagram->nodes[0]->id);
        $this->assertSame('Web', $diagram->nodes[0]->groupId);
        $this->assertSame('self', $diagram->relationships[0]->label?->text);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            architecture
                title Checkout platform
                group Web [Web tier]
                    component App [Frontend app]
                    component Api [Checkout API]
                App -> Api : calls
            MERMAID;

        $parser = new ArchitectureDiagramParser();
        $serializer = new ArchitectureDiagramSerializer();

        $mermaid = $serializer->serialize($parser->parse($source));

        $this->assertSame($mermaid, $serializer->serialize($parser->parse($mermaid)));
    }

    public function testRejectsUnsupportedSyntaxWithLineContext(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\nservice Api [Checkout API]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported architecture diagram syntax at line 2: "service Api [Checkout API]"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('service Api [Checkout API]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsUnexpectedHeaderWithLineContext(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("block\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected "architecture" header at line 1: "block"', $exception->getMessage());
            $this->assertSame('parser.expected_header', $exception->getDiagnostic()->code);
            $this->assertSame('block', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsMissingHeaderWithLineContext(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("%% comment\n\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Missing "architecture" header at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.missing_header', $exception->getDiagnostic()->code);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsRelationshipBeforeNodeWithLineContext(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\nApp -> Api : calls\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Architecture relationship references unknown node "App" at line 2: "App -> Api : calls"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('App -> Api : calls', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateNodeWithStableDiagnostic(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\ncomponent App\ncomponent App\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate architecture node id "App" at line 3: "component App"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('component App', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateGroupWithStableDiagnostic(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\ngroup Web [Web tier]\ngroup Web [Web tier]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate architecture group id "Web" at line 3: "group Web [Web tier]"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('group Web [Web tier]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsUnknownRelationshipNodeWithStableDiagnostic(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\ncomponent App\nApp -> Api : calls\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Architecture relationship references unknown node "Api" at line 3: "App -> Api : calls"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('App -> Api : calls', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsEmptyNodeLabel(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\ncomponent App []\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Architecture node label must not be empty at line 2: "component App []"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsEmptyGroupLabel(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\ngroup Web []\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Architecture group label must not be empty at line 2: "group Web []"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('group Web []', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsRelationshipBeforeNodeWithStableDiagnostic(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\nApp -> Api : calls\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Architecture relationship references unknown node "App" at line 2: "App -> Api : calls"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('App -> Api : calls', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsEmptyRelationshipLabel(): void
    {
        try {
            (new ArchitectureDiagramParser())->parse("architecture\ncomponent App\ncomponent Api\nApp -> Api : \n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Architecture relationship label must not be empty at line 4: "App -> Api :"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('App -> Api :', $exception->getDiagnostic()->source?->content);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
        }
    }
}
