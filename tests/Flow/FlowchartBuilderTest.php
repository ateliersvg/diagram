<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Flow\FlowEdge;
use Atelier\Diagram\Flow\FlowNode;
use Atelier\Diagram\Flow\FlowSubgraph;
use Atelier\Diagram\Model\Direction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flowchart::class)]
#[CoversClass(FlowchartBuilder::class)]
#[CoversClass(FlowSubgraph::class)]
final class FlowchartBuilderTest extends TestCase
{
    public function testBuildsNodesAndEdges(): void
    {
        $flowchart = (new FlowchartBuilder())
            ->direction(Direction::LeftToRight)
            ->title('My Flow')
            ->node('A', 'Start')
            ->edge('A', 'B', 'go')
            ->node('B', 'Done')
            ->subgraph('inner', 'Inner', ['B'], 'checkout', 1)
            ->subgraph('checkout', 'Checkout', ['A', 'B'])
            ->build();

        $this->assertSame('My Flow', $flowchart->title?->text);
        $this->assertSame(Direction::LeftToRight, $flowchart->direction);
        $this->assertSame(['A', 'B'], array_map(static fn ($node): string => $node->id, $flowchart->nodes));
        $this->assertSame('Done', $flowchart->nodes[1]->label);
        $this->assertSame('go', $flowchart->edges[0]->label?->text);
        $this->assertSame('Inner', $flowchart->subgraphs[0]->label);
        $this->assertSame('checkout', $flowchart->subgraphs[0]->parentId);
        $this->assertSame('Checkout', $flowchart->subgraphs[1]->label);
        $this->assertSame(['A', 'B'], $flowchart->subgraphs[1]->nodeIds);
    }

    public function testBuilderRejectsEmptyFlowchart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one node');

        (new FlowchartBuilder())->build();
    }

    public function testRejectsEmptyNodes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one node');

        new Flowchart(Direction::TopToBottom, [], []);
    }

    public function testRejectsDuplicateNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate flow node id "A"');

        new Flowchart(Direction::TopToBottom, [new FlowNode('A', 'A'), new FlowNode('A', 'B')], []);
    }

    public function testRejectsEdgeWithUnknownFrom(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flow edge references unknown node "B"');

        new Flowchart(Direction::TopToBottom, [new FlowNode('A', 'A')], [new FlowEdge('B', 'A')]);
    }

    public function testRejectsEdgeWithUnknownTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flow edge references unknown node "B"');

        new Flowchart(Direction::TopToBottom, [new FlowNode('A', 'A')], [new FlowEdge('A', 'B')]);
    }

    public function testRejectsDuplicateSubgraphId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate flow subgraph id "g"');

        new Flowchart(
            Direction::TopToBottom,
            [new FlowNode('A', 'A')],
            [],
            null,
            [new FlowSubgraph('g', 'G1', ['A']), new FlowSubgraph('g', 'G2', ['A'])],
        );
    }

    public function testRejectsSubgraphWithUnknownNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown node "Z"');

        new Flowchart(
            Direction::TopToBottom,
            [new FlowNode('A', 'A')],
            [],
            null,
            [new FlowSubgraph('g', 'G', ['Z'])],
        );
    }

    public function testRejectsSubgraphWithUnknownParent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown parent subgraph "nope"');

        new Flowchart(
            Direction::TopToBottom,
            [new FlowNode('A', 'A')],
            [],
            null,
            [new FlowSubgraph('g', 'G', ['A'], 'nope')],
        );
    }

    public function testSubgraphRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subgraph id must be non-empty');

        new FlowSubgraph('   ', 'G', ['A']);
    }

    public function testSubgraphRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subgraph label must be non-empty');

        new FlowSubgraph('g', '   ', ['A']);
    }

    public function testSubgraphRejectsEmptyNodeIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one node');

        new FlowSubgraph('g', 'G', []);
    }

    public function testSubgraphRejectsNegativeDepth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('depth must not be negative');

        new FlowSubgraph('g', 'G', ['A'], null, -1);
    }
}
