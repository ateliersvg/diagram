<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\Block\BlockGroup;
use Atelier\Diagram\Block\BlockNode;
use Atelier\Diagram\Block\BlockRelationship;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\BlockDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Markdown\BlockDiagramSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockDiagramParser::class)]
#[CoversClass(BlockDiagramSerializer::class)]
#[CoversClass(BlockDiagramBuilder::class)]
#[CoversClass(BlockDiagram::class)]
#[CoversClass(BlockNode::class)]
#[CoversClass(BlockGroup::class)]
#[CoversClass(BlockRelationship::class)]
final class BlockDiagramParserTest extends TestCase
{
    public function testParsesBlockSubset(): void
    {
        $diagram = (new BlockDiagramParser())->parse(<<<'MERMAID'
            block
                title Layout kernel
                block Solver [LayoutSolver]
                block Grid [Grid]
                group Primitives [Primitives]
                    block Rect [Rect]
                    block Insets [Insets]
                end
                Solver -> Grid : solves
            MERMAID);

        $this->assertSame('Layout kernel', $diagram->title?->text);
        $this->assertSame('LayoutSolver', $diagram->nodes[0]->label);
        $this->assertSame('Primitives', $diagram->groups[0]->label);
        $this->assertSame(['Rect', 'Insets'], $diagram->groups[0]->nodeIds);
        $this->assertSame('solves', $diagram->relationships[0]->label?->text);
    }

    public function testRoundTripsCanonicalMermaid(): void
    {
        $source = <<<'MERMAID'
            block
                title Layout kernel
                block Solver [LayoutSolver]
                group Primitives [Primitives]
                    block Rect [Rect]
                end
                Solver -> Rect : places
            MERMAID;

        $parser = new BlockDiagramParser();
        $serializer = new BlockDiagramSerializer();

        $this->assertSame($serializer->serialize($parser->parse($source)), $serializer->serialize($parser->parse($serializer->serialize($parser->parse($source)))));
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new BlockDiagramParser())->parse("block\ncomponent Api [API]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported block diagram syntax at line 2: "component Api [API]"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('component Api [API]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyNodeLabelThrowsAtSourceLine(): void
    {
        try {
            (new BlockDiagramParser())->parse("block\n    block Solver [   ]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Block label must not be empty at line 2: "block Solver [   ]"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('block Solver [   ]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyGroupLabelThrowsAtSourceLine(): void
    {
        try {
            (new BlockDiagramParser())->parse("block\n    group Empty [   ]\n    block Solver [Solver]\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Block group label must not be empty at line 2: "group Empty [   ]"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('group Empty [   ]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnexpectedEndThrowsAtSourceLine(): void
    {
        try {
            (new BlockDiagramParser())->parse("block\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unexpected block diagram block end at line 2: "end"', $exception->getMessage());
            $this->assertSame('parser.unexpected_block_end', $exception->getDiagnostic()->code);
            $this->assertSame('end', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testNestedGroupThrowsAtSourceLine(): void
    {
        try {
            (new BlockDiagramParser())->parse("block\ngroup A [A]\ngroup B [B]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Nested block groups are not supported yet at line 3: "group B [B]"', $exception->getMessage());
            $this->assertSame('parser.nested_block', $exception->getDiagnostic()->code);
            $this->assertSame('group B [B]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnclosedGroupThrowsAtOpeningLine(): void
    {
        try {
            (new BlockDiagramParser())->parse("block\ngroup A [A]\nblock Solver\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed block group "A" at line 2: "group A [A]"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testDuplicateGroupThrowsAtSourceLine(): void
    {
        try {
            (new BlockDiagramParser())->parse(<<<'MERMAID'
                block
                    block Solver [LayoutSolver]
                    group Primitives [Primitives]
                        block Rect [Rect]
                    end
                    group Primitives [Duplicate]
                        block Other [Other]
                    end
                MERMAID);
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate block group id "Primitives" at line 6: "group Primitives [Duplicate]"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('group Primitives [Duplicate]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(6, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(6, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyGroupThrowsAtHeaderLine(): void
    {
        try {
            (new BlockDiagramParser())->parse(<<<'MERMAID'
                block
                    block Solver [LayoutSolver]
                    group Empty [Empty]
                    end
                MERMAID);
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Block group "Empty" must contain at least one block at line 1: "block"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('block', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesViaHeaderEntryPoint(): void
    {
        $source = "block\ntitle Layout kernel\nblock Solver [LayoutSolver]\ngroup Primitives [Primitives]\nblock Rect [Rect]\nend\nSolver -> Rect : places\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new BlockDiagramParser())->parse($source, null, $header);

        $this->assertSame('Layout kernel', $diagram->title?->text);
        $this->assertSame('LayoutSolver', $diagram->nodes[0]->label);
        $this->assertSame('Primitives', $diagram->groups[0]->label);
        $this->assertSame('places', $diagram->relationships[0]->label?->text);
    }

    public function testUnclosedGroupViaHeaderThrowsAtOpeningLine(): void
    {
        $source = "block\ngroup A [A]\nblock Solver\n";
        $header = HeaderParser::firstSignificantLine($source);

        try {
            (new BlockDiagramParser())->parse($source, null, $header);
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unclosed block group "A" at line 2: "group A [A]"', $exception->getMessage());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }
}
