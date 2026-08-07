<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Markdown;

use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Flow\FlowEdge;
use Atelier\Diagram\Flow\FlowNode;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Title;
use Atelier\Diagram\Renderer\Markdown\FlowchartSerializer;
use Atelier\Diagram\Renderer\Markdown\StateDiagramSerializer;
use Atelier\Diagram\State\State;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\Transition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Representable edge cases the round-trip suite does not exercise: the
 * flowchart title and unlabeled-edge branches, the state diagram's
 * `direction LR` header, and the explicit-declaration ordering branch the
 * serializer takes when auto-declaration would misplace a state.
 */
#[CoversClass(FlowchartSerializer::class)]
#[CoversClass(StateDiagramSerializer::class)]
final class MermaidSerializerEdgeCasesTest extends TestCase
{
    public function testFlowchartSerializesTitleAndUnlabeledEdge(): void
    {
        $flowchart = new Flowchart(
            Direction::TopToBottom,
            [new FlowNode('A', 'A'), new FlowNode('B', 'B')],
            [new FlowEdge('A', 'B')],
            new Title('Pipeline'),
        );

        $this->assertSame(
            "flowchart TD\n    title Pipeline\n    A[A]\n    B[B]\n    A --> B\n",
            (new FlowchartSerializer())->serialize($flowchart),
        );
    }

    public function testStateDiagramEmitsDirectionAndExplicitOrdering(): void
    {
        // A --> C with B declared between them: auto-declaring both endpoints
        // would emit C before B, so the serializer declares A and B explicitly
        // and leaves only C to the transition's auto-declaration.
        $diagram = new StateDiagram(
            Direction::LeftToRight,
            [new State('A'), new State('B'), new State('C')],
            [new Transition('A', 'C')],
        );

        $this->assertSame(
            "stateDiagram-v2\n    direction LR\n    state \"A\" as A\n    state \"B\" as B\n    A --> C\n",
            (new StateDiagramSerializer())->serialize($diagram),
        );
    }
}
