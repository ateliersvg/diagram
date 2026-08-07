<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\RequirementDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\RequirementDiagramSerializer;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementDiagramBuilder;
use Atelier\Diagram\Requirement\RequirementNode;
use Atelier\Diagram\Requirement\RequirementNodeKind;
use Atelier\Diagram\Requirement\RequirementRelationship;
use Atelier\Diagram\Requirement\RequirementRelationshipKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequirementDiagramParser::class)]
#[CoversClass(RequirementDiagramSerializer::class)]
#[CoversClass(RequirementDiagram::class)]
#[CoversClass(RequirementDiagramBuilder::class)]
#[CoversClass(RequirementNode::class)]
#[CoversClass(RequirementNodeKind::class)]
#[CoversClass(RequirementRelationship::class)]
#[CoversClass(RequirementRelationshipKind::class)]
final class RequirementDiagramParserTest extends TestCase
{
    public function testParsesRequirementDiagramSubset(): void
    {
        $diagram = (new RequirementDiagramParser())->parse(<<<'MERMAID'
            requirementDiagram
                requirement checkout {
                    id: REQ-1
                    text: Customer can checkout
                    risk: medium
                    verifymethod: test
                }
                element cart {
                    type: component
                }
                cart - satisfies -> checkout
            MERMAID);

        $this->assertSame('checkout', $diagram->nodes[0]->id);
        $this->assertSame(RequirementNodeKind::Requirement, $diagram->nodes[0]->kind);
        $this->assertSame('Customer can checkout', $diagram->nodes[0]->fields['text']);
        $this->assertSame('cart', $diagram->nodes[1]->id);
        $this->assertSame(RequirementNodeKind::Element, $diagram->nodes[1]->kind);
        $this->assertSame(RequirementRelationshipKind::Satisfies, $diagram->relationships[0]->kind);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            requirementDiagram
                requirement checkout {
                    id: REQ-1
                    text: Customer can checkout
                }
                element cart {
                    type: component
                }
                cart - satisfies -> checkout
            MERMAID;

        $parser = new RequirementDiagramParser();
        $serializer = new RequirementDiagramSerializer();

        $this->assertSame($serializer->serialize($parser->parse($source)), $serializer->serialize($parser->parse($serializer->serialize($parser->parse($source)))));
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new RequirementDiagramParser())->parse("requirementDiagram\ncart satisfies checkout\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported requirement diagram syntax at line 2: "cart satisfies checkout"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('cart satisfies checkout', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnsupportedBlockSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new RequirementDiagramParser())->parse("requirementDiagram\nrequirement checkout {\n    risk medium\n}\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported requirement diagram node block syntax at line 3: "risk medium"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('risk medium', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsUnknownRelationshipNodeWithStableDiagnostic(): void
    {
        try {
            (new RequirementDiagramParser())->parse("requirementDiagram\nrequirement checkout {\n    id: REQ-1\n}\ncart - satisfies -> checkout\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Requirement relationship references unknown node "cart" at line 1: "requirementDiagram"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('requirementDiagram', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateNodeKindWithStableDiagnostic(): void
    {
        try {
            (new RequirementDiagramParser())->parse("requirementDiagram\nrequirement checkout {\n    id: REQ-1\n}\nelement checkout {\n    type: component\n}\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Requirement diagram node "checkout" cannot be both "requirement" and "element" at line 5: "element checkout {"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('element checkout {', $exception->getDiagnostic()->source?->content);
            $this->assertSame(5, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(5, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnclosedNodeThrowsAtOpeningLine(): void
    {
        try {
            (new RequirementDiagramParser())->parse("requirementDiagram\nrequirement checkout {\n    id: REQ-1\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed requirement diagram node "checkout" at line 2: "requirement checkout {"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesViaHeaderEntryPoint(): void
    {
        $source = "requirementDiagram\nrequirement checkout {\n    id: REQ-1\n}\nelement cart {\n    type: component\n}\ncart - satisfies -> checkout\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new RequirementDiagramParser())->parse($source, null, $header);

        $this->assertSame('checkout', $diagram->nodes[0]->id);
        $this->assertSame('cart', $diagram->nodes[1]->id);
        $this->assertSame(RequirementRelationshipKind::Satisfies, $diagram->relationships[0]->kind);
    }

    public function testUnclosedNodeViaHeaderThrowsAtOpeningLine(): void
    {
        $source = "requirementDiagram\nrequirement checkout {\n    id: REQ-1\n";
        $header = HeaderParser::firstSignificantLine($source);

        try {
            (new RequirementDiagramParser())->parse($source, null, $header);
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed requirement diagram node "checkout" at line 2: "requirement checkout {"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }
}
