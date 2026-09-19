<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Parser\FlowchartParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowchartParser::class)]
final class FlowchartParserTest extends TestCase
{
    public function testParsesANodeDeclaredInsideAnEdge(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                A["Request"] --> B["Construct candidates"]
            MERMAID;

        $flowchart = (new FlowchartParser())->parse($source);

        $this->assertCount(2, $flowchart->nodes);
        $this->assertSame('Request', self::labelOf($flowchart, 'A'));
        $this->assertSame('Construct candidates', self::labelOf($flowchart, 'B'));
        $this->assertCount(1, $flowchart->edges);
        $this->assertSame('A', $flowchart->edges[0]->from);
        $this->assertSame('B', $flowchart->edges[0]->to);
    }

    public function testParsesALabelledEdgeWhoseTargetDeclaresAShape(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                B -->|No| D["Exclude short source"]
            MERMAID;

        $flowchart = (new FlowchartParser())->parse($source);

        $this->assertSame('Exclude short source', self::labelOf($flowchart, 'D'));
        $this->assertCount(1, $flowchart->edges);
        $this->assertSame('No', $flowchart->edges[0]->label?->text);
    }

    public function testParsesAShapeOnEitherEndAlone(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                A[Start] --> B
                C --> D[Stop]
            MERMAID;

        $flowchart = (new FlowchartParser())->parse($source);

        $this->assertSame('Start', self::labelOf($flowchart, 'A'));
        $this->assertSame('B', self::labelOf($flowchart, 'B'), 'a bare id is its own label');
        $this->assertSame('C', self::labelOf($flowchart, 'C'));
        $this->assertSame('Stop', self::labelOf($flowchart, 'D'));
        $this->assertCount(2, $flowchart->edges);
    }

    public function testKeepsADeclaredLabelWhenTheNodeIsNamedAgainInAnEdge(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                A[Cart]
                A --> B
            MERMAID;

        $flowchart = (new FlowchartParser())->parse($source);

        $this->assertSame('Cart', self::labelOf($flowchart, 'A'));
    }

    public function testStripsTheQuotesMermaidUsesToEscapeALabel(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                A["Loose-first"]
            MERMAID;

        $flowchart = (new FlowchartParser())->parse($source);

        $this->assertSame('Loose-first', self::labelOf($flowchart, 'A'));
    }

    public function testRejectsAnEmptyNodeLabelRatherThanCallingItUnsupported(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                A[   ]
            MERMAID;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        (new FlowchartParser())->parse($source);
    }

    public function testStillRejectsAShapeItDoesNotSupport(): void
    {
        $source = <<<'MERMAID'
            flowchart TD
                A((Circle))
            MERMAID;

        $this->expectException(ParseException::class);

        (new FlowchartParser())->parse($source);
    }

    public function testParsesFlowchartSubset(): void
    {
        $source = <<<'MERMAID'
            flowchart LR
                title Checkout
                subgraph checkout [Checkout flow]
                    A[Cart]
                    subgraph payment [Payment flow]
                        B[Payment]
                    end
                end
                A -->|pay| B
            MERMAID;

        $flowchart = (new FlowchartParser())->parse($source);

        $this->assertSame(Direction::LeftToRight, $flowchart->direction);
        $this->assertSame('Checkout', $flowchart->title?->text);
        $this->assertSame('Cart', $flowchart->nodes[0]->label);
        $this->assertSame('pay', $flowchart->edges[0]->label?->text);
        $this->assertSame('payment', $flowchart->subgraphs[0]->id);
        $this->assertSame('checkout', $flowchart->subgraphs[0]->parentId);
        $this->assertSame(['B'], $flowchart->subgraphs[0]->nodeIds);
        $this->assertSame('checkout', $flowchart->subgraphs[1]->id);
        $this->assertNull($flowchart->subgraphs[1]->parentId);
        $this->assertSame(['A', 'B'], $flowchart->subgraphs[1]->nodeIds);
    }

    public function testAutoDeclaresEdgeEndpoints(): void
    {
        $flowchart = (new FlowchartParser())->parse("flowchart TD\nA --> B\n");

        $this->assertSame(['A', 'B'], array_map(static fn ($node): string => $node->id, $flowchart->nodes));
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nA((unsupported))\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported flowchart syntax at line 2: "A((unsupported))"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('A((unsupported))', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnexpectedEndThrowsAtSourceLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unexpected flowchart block end at line 2: "end"', $exception->getMessage());
            $this->assertSame('parser.unexpected_block_end', $exception->getDiagnostic()->code);
            $this->assertSame('end', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyFlowchartThrowsAtHeaderLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Flowchart must contain at least one node at line 1: "flowchart TD"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('flowchart TD', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnclosedSubgraphThrowsAtOpeningLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nsubgraph checkout [Checkout]\nA[Cart]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame(2, $exception->getLineNumber());
            $this->assertSame('subgraph checkout [Checkout]', $exception->getSourceLine());
            $this->assertSame('parser.unclosed_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('subgraph checkout [Checkout]', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testEmptySubgraphThrowsAtOpeningLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nsubgraph checkout [Checkout]\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Flow subgraph must contain at least one node at line 2: "subgraph checkout [Checkout]"', $exception->getMessage());
            $this->assertSame('parser.empty_block', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('subgraph checkout [Checkout]', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testDuplicateSubgraphThrowsAtHeaderLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nsubgraph checkout [Checkout]\nA[Cart]\nend\nsubgraph checkout [Duplicate]\nB[Pay]\nend\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate flow subgraph id "checkout" at line 1: "flowchart TD"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('flowchart TD', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testEmptyNodeLabelThrowsAtSourceLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nA[   ]\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Flow node label must not be empty at line 2: "A[   ]"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('A[   ]', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testEmptyEdgeLabelThrowsAtSourceLine(): void
    {
        try {
            (new FlowchartParser())->parse("flowchart TD\nA -->|   | B\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Flow edge label must not be empty at line 2: "A -->|   | B"', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
            $this->assertSame('A -->|   | B', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    private static function labelOf(\Atelier\Diagram\Flow\Flowchart $flowchart, string $id): ?string
    {
        foreach ($flowchart->nodes as $node) {
            if ($node->id === $id) {
                return $node->label;
            }
        }

        return null;
    }
}
