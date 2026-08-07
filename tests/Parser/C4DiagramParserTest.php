<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\C4\C4Boundary;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\C4\C4DiagramBuilder;
use Atelier\Diagram\C4\C4Element;
use Atelier\Diagram\C4\C4ElementKind;
use Atelier\Diagram\C4\C4Relationship;
use Atelier\Diagram\C4\C4View;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\C4DiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\C4DiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(C4DiagramParser::class)]
#[CoversClass(C4DiagramSerializer::class)]
#[CoversClass(C4DiagramBuilder::class)]
#[CoversClass(C4Diagram::class)]
#[CoversClass(C4Boundary::class)]
#[CoversClass(C4Element::class)]
#[CoversClass(C4Relationship::class)]
#[CoversClass(C4ElementKind::class)]
#[CoversClass(C4View::class)]
final class C4DiagramParserTest extends TestCase
{
    public function testParsesC4ContainerSubset(): void
    {
        $diagram = (new C4DiagramParser())->parse(<<<'MERMAID'
            C4Container
                title Shop platform
                Person(buyer, "Buyer", "Places orders")
                System_Ext(stripe, "Stripe", "Payment provider")
                System_Boundary(shop, "Shop Platform") {
                    Container(web, "Web App", "Symfony", "Checkout UI")
                    ContainerDb(db, "Orders DB", "PostgreSQL", "Order state")
                }
                Rel(buyer, web, "uses")
                Rel(web, db, "writes", "SQL")
            MERMAID);

        $this->assertSame(C4View::Container, $diagram->view);
        $this->assertSame('Shop platform', $diagram->title?->text);
        $this->assertSame('shop', $diagram->boundaries[0]->id);
        $this->assertSame('buyer', $diagram->elements[0]->id);
        $this->assertSame(C4ElementKind::SystemExternal, $diagram->elements[1]->kind);
        $this->assertSame('shop', $diagram->elements[2]->boundaryId);
        $this->assertSame('SQL', $diagram->relationships[1]->technology?->text);
    }

    public function testParsesWithPreMatchedHeader(): void
    {
        $source = <<<'MERMAID'
            C4Context
                Person(user, "User")
                System(app, "Application")
                Rel(user, app, "uses")
            MERMAID;
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new C4DiagramParser())->parse($source, null, $header);

        $this->assertSame(C4View::Context, $diagram->view);
        $this->assertSame('User', $diagram->elements[0]->label->text);
        $this->assertSame('uses', $diagram->relationships[0]->label->text);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            C4Component
                title Checkout API
                System_Boundary(api, "Checkout API") {
                    Component(orders, "Orders", "PHP")
                    ComponentDb(db, "Orders DB", "PostgreSQL")
                }
                Rel(orders, db, "writes")
            MERMAID;

        $parser = new C4DiagramParser();
        $serializer = new C4DiagramSerializer();

        $mermaid = $serializer->serialize($parser->parse($source));

        $this->assertSame($mermaid, $serializer->serialize($parser->parse($mermaid)));
    }

    public function testRejectsUnsupportedSyntaxWithLineContext(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Context\nDeployment_Node(node, \"Node\")\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported C4 diagram syntax at line 2: "Deployment_Node(node, "Node")"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsNestedBoundary(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Container\nSystem_Boundary(a, \"A\") {\nSystem_Boundary(b, \"B\") {\n}\n}\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Nested C4 boundaries are not supported at line 3: "System_Boundary(b, "B") {"', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsUnexpectedBoundaryEnd(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Context\n}\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unexpected C4 boundary block end at line 2: "}"', $exception->getMessage());
            $this->assertSame('parser.unexpected_block_end', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsUnsupportedPlainLine(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Context\nthis is not a call\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported C4 diagram syntax at line 2: "this is not a call"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsUnclosedQuotedArgument(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Context\nPerson(user, \"User)\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Invalid C4 element argument list at line 2: "Person(user, "User)"', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsArgumentListWithoutComma(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Context\nPerson(\"user\" \"User\")\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Invalid C4 element argument list at line 2: "Person("user" "User")"', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsWrongArgumentCount(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Context\nRel(user, app)\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Invalid C4 relationship argument count at line 2: "Rel(user, app)"', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsUnclosedBoundary(): void
    {
        try {
            (new C4DiagramParser())->parse("C4Container\nSystem_Boundary(shop, \"Shop\") {\nContainer(api, \"API\")\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed C4 boundary at line 2: "System_Boundary(shop, "Shop") {"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
        }
    }
}
