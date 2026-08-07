<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;
use Atelier\Diagram\Parser\MindmapDiagramParser;
use Atelier\Diagram\Renderer\Markdown\MindmapDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MindmapDiagramParser::class)]
#[CoversClass(MindmapDiagramSerializer::class)]
#[CoversClass(MindmapDiagram::class)]
#[CoversClass(MindmapDiagramBuilder::class)]
final class MindmapDiagramParserTest extends TestCase
{
    public function testParsesMindmapSubset(): void
    {
        $diagram = (new MindmapDiagramParser())->parse(<<<'MERMAID'
            mindmap
              root((Atelier))
                Layout
                  Grid
                  Stack
                Diagram
                  Timeline
                  ER
            MERMAID);

        $this->assertSame('Atelier', $diagram->root->label);
        $this->assertSame('Layout', $diagram->root->children[0]->label);
        $this->assertSame('Grid', $diagram->root->children[0]->children[0]->label);
        $this->assertSame('ER', $diagram->root->children[1]->children[1]->label);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            mindmap
              root((Atelier))
                Layout
                  Grid
                Diagram
                  Sequence
            MERMAID;

        $parser = new MindmapDiagramParser();
        $serializer = new MindmapDiagramSerializer();

        $this->assertSame($serializer->serialize($parser->parse($source)), $serializer->serialize($parser->parse($serializer->serialize($parser->parse($source)))));
    }

    public function testRejectsUnexpectedHeaderWithStableDiagnostic(): void
    {
        try {
            (new MindmapDiagramParser())->parse("flowchart TD\n  root((Atelier))\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected "mindmap" header at line 1: "flowchart TD"', $exception->getMessage());
            $this->assertSame('parser.expected_header', $exception->getDiagnostic()->code);
            $this->assertSame('flowchart TD', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsUnindentedNodeWithStableDiagnostic(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\nroot((Atelier))\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap nodes must be indented under the header at line 2: "root((Atelier))"', $exception->getMessage());
            $this->assertSame('parser.indentation_expected', $exception->getDiagnostic()->code);
            $this->assertSame('root((Atelier))', $exception->getSourceLine());
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testBadIndentationThrowsAtSourceLine(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n  root((Atelier))\n   Layout\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap indentation must use 2 spaces per level at line 3: "   Layout"', $exception->getMessage());
            $this->assertSame('parser.indentation_step', $exception->getDiagnostic()->code);
            $this->assertSame('   Layout', $exception->getSourceLine());
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testTabsThrowAtSourceLine(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n\troot((Atelier))\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame("Mindmap indentation must use spaces only at line 2: \"\troot((Atelier))\"", $exception->getMessage());
            $this->assertSame('parser.indentation_tabs', $exception->getDiagnostic()->code);
            $this->assertSame("\troot((Atelier))", $exception->getSourceLine());
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testSkippedIndentationThrowsAtSourceLine(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n  root((Atelier))\n      Grid\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap indentation cannot skip levels at line 3: "      Grid"', $exception->getMessage());
            $this->assertSame('parser.indentation_skip', $exception->getDiagnostic()->code);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyDiagramThrowsAtHeaderLine(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap diagram must contain exactly one root at line 1: "mindmap"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('mindmap', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsMultipleRoots(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n  root((Atelier))\n  Other\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap diagram must contain exactly one root at line 3: "  Other"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('  Other', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateRootWithStableDiagnostic(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n  root((Atelier))\n  root((Other))\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap diagram must contain exactly one root at line 3: "  root((Other))"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('  root((Other))', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsChildShapeSyntax(): void
    {
        try {
            (new MindmapDiagramParser())->parse("mindmap\n  root((Atelier))\n    Child((Nope))\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Mindmap v0 only supports root((Text)) shape syntax on the root node at line 3: "    Child((Nope))"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('    Child((Nope))', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }
}
