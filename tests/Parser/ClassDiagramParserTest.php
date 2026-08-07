<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\ClassDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\ClassDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassDiagramParser::class)]
#[CoversClass(ClassDiagramSerializer::class)]
#[CoversClass(ClassDiagram::class)]
#[CoversClass(ClassDiagramBuilder::class)]
final class ClassDiagramParserTest extends TestCase
{
    public function testParsesClassDiagramSubset(): void
    {
        $diagram = (new ClassDiagramParser())->parse(<<<'MERMAID'
            classDiagram
                class User
                User : +id int
                User --> Order : places
            MERMAID);

        $this->assertSame('User', $diagram->classes[0]->id);
        $this->assertSame('+id int', $diagram->classes[0]->members[0]->text);
        $this->assertSame('Order', $diagram->classes[1]->id);
        $this->assertSame('places', $diagram->relations[0]->label?->text);
    }

    public function testParsesWithPreMatchedHeader(): void
    {
        $source = "classDiagram\n    class User\n    User --> Order : places\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new ClassDiagramParser())->parse($source, null, $header);

        $this->assertSame('User', $diagram->classes[0]->id);
        $this->assertSame('Order', $diagram->classes[1]->id);
        $this->assertSame('places', $diagram->relations[0]->label?->text);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<< 'MERMAID'
            classDiagram
                class User
                User : +id int
                User --> Order : places
            MERMAID;

        $parser = new ClassDiagramParser();
        $serializer = new ClassDiagramSerializer();

        $this->assertSame($serializer->serialize($parser->parse($source)), $serializer->serialize($parser->parse($serializer->serialize($parser->parse($source)))));
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new ClassDiagramParser())->parse("classDiagram\nUser <|-- Admin\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported class diagram syntax at line 2: "User <|-- Admin"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('User <|-- Admin', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyMemberLabelThrowsStableDiagnostic(): void
    {
        try {
            (new ClassDiagramParser())->parse("classDiagram\nUser :\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Class member label must not be empty at line 2: "User :"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyRelationLabelThrowsStableDiagnostic(): void
    {
        try {
            (new ClassDiagramParser())->parse("classDiagram\nclass User\nUser --> Order :\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Class relation label must not be empty at line 3: "User --> Order :"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('User --> Order :', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnexpectedHeaderThrowsAtSourceLine(): void
    {
        try {
            (new ClassDiagramParser())->parse("stateDiagram-v2\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected "classDiagram" header at line 1: "stateDiagram-v2"', $exception->getMessage());
            $this->assertSame('parser.expected_header', $exception->getDiagnostic()->code);
            $this->assertSame('stateDiagram-v2', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testMissingHeaderThrowsAtLineOne(): void
    {
        try {
            (new ClassDiagramParser())->parse("%% comment\n\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Missing "classDiagram" header at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.missing_header', $exception->getDiagnostic()->code);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testBuildErrorThrowsAtHeaderLine(): void
    {
        try {
            (new ClassDiagramParser())->parse("classDiagram\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Class diagram must contain at least one class at line 1: "classDiagram"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('classDiagram', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }
}
