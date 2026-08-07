<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Er\ErAttribute;
use Atelier\Diagram\Er\ErCardinality;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Er\ErEntity;
use Atelier\Diagram\Er\ErRelationship;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\ErDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\ErDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErDiagramParser::class)]
#[CoversClass(ErDiagramSerializer::class)]
#[CoversClass(ErDiagram::class)]
#[CoversClass(ErDiagramBuilder::class)]
#[CoversClass(ErEntity::class)]
#[CoversClass(ErAttribute::class)]
#[CoversClass(ErRelationship::class)]
#[CoversClass(ErCardinality::class)]
final class ErDiagramParserTest extends TestCase
{
    public function testParsesErDiagramSubset(): void
    {
        $diagram = (new ErDiagramParser())->parse(<<<'MERMAID'
            erDiagram
                CUSTOMER {
                    string name
                    string email
                }
                ORDER {
                    int id
                    decimal total
                }
                CUSTOMER ||--o{ ORDER : places
            MERMAID);

        $this->assertSame('CUSTOMER', $diagram->entities[0]->id);
        $this->assertSame('string', $diagram->entities[0]->attributes[0]->type);
        $this->assertSame('email', $diagram->entities[0]->attributes[1]->name);
        $this->assertSame('ORDER', $diagram->entities[1]->id);
        $this->assertSame(ErCardinality::ExactlyOne, $diagram->relationships[0]->fromCardinality);
        $this->assertSame(ErCardinality::ZeroOrMore, $diagram->relationships[0]->toCardinality);
        $this->assertSame('places', $diagram->relationships[0]->label?->text);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            erDiagram
                CUSTOMER {
                    string email
                }
                ORDER {
                    decimal total
                }
                CUSTOMER ||--o{ ORDER : places
            MERMAID;

        $parser = new ErDiagramParser();
        $serializer = new ErDiagramSerializer();

        $this->assertSame($serializer->serialize($parser->parse($source)), $serializer->serialize($parser->parse($serializer->serialize($parser->parse($source)))));
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new ErDiagramParser())->parse("erDiagram\nCUSTOMER }o--|| ORDER\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported ER diagram syntax at line 2: "CUSTOMER }o--|| ORDER"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('CUSTOMER }o--|| ORDER', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyDiagramThrowsAtHeaderLine(): void
    {
        try {
            (new ErDiagramParser())->parse("erDiagram\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('ER diagram must contain at least one entity at line 1: "erDiagram"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('erDiagram', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnsupportedAttributeSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new ErDiagramParser())->parse("erDiagram\nCUSTOMER {\n    string\n}\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported ER entity block syntax at line 3: "string"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('string', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnclosedEntityThrowsAtOpeningLine(): void
    {
        try {
            (new ErDiagramParser())->parse("erDiagram\nCUSTOMER {\n    string email\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed ER entity "CUSTOMER" at line 2: "CUSTOMER {"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesViaHeaderEntryPoint(): void
    {
        $source = "erDiagram\nCUSTOMER {\n    string email\n}\nORDER {\n    decimal total\n}\nCUSTOMER ||--o{ ORDER : places\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new ErDiagramParser())->parse($source, null, $header);

        $this->assertSame('CUSTOMER', $diagram->entities[0]->id);
        $this->assertSame('ORDER', $diagram->entities[1]->id);
        $this->assertSame('places', $diagram->relationships[0]->label?->text);
    }

    public function testUnclosedEntityViaHeaderThrowsAtOpeningLine(): void
    {
        $source = "erDiagram\nCUSTOMER {\n    string email\n";
        $header = HeaderParser::firstSignificantLine($source);

        try {
            (new ErDiagramParser())->parse($source, null, $header);
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed ER entity "CUSTOMER" at line 2: "CUSTOMER {"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }
}
