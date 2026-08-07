<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\State;

use Atelier\Diagram\Layout\State\StateLayoutEngine;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\State\State;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\StateDiagramBuilder;
use Atelier\Diagram\State\Transition;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateLayoutEngine::class)]
#[CoversClass(StateDiagram::class)]
#[CoversClass(StateDiagramBuilder::class)]
#[CoversClass(State::class)]
#[CoversClass(Transition::class)]
final class StateLayoutEngineTest extends TestCase
{
    use ExampleFixtures;

    /**
     * End-to-end snapshot: builder -> layout -> SvgRenderer against the
     * frozen, visually reviewed examples/output/state-tb.svg artifact.
     */
    public function testLayoutMatchesFrozenStateMachineSnapshot(): void
    {
        self::loadExample('state-machine.php');

        $diagram = buildStateMachineDiagram(Direction::TopToBottom, 'Order lifecycle');
        $scene = (new StateLayoutEngine())->layout($diagram, Theme::default());
        $svg = (new SvgRenderer())->render($scene);

        $snapshot = file_get_contents(__DIR__.'/__snapshots__/state-machine-tb.svg');
        $this->assertNotFalse($snapshot);
        $this->assertSame(trim($snapshot), trim($svg));
    }

    public function testThemeCanSetMinimumStateNodeSize(): void
    {
        $diagram = (new StateDiagramBuilder())
            ->direction(Direction::LeftToRight)
            ->initial('A')
            ->transition('A', 'B', 'next')
            ->final('B')
            ->build();

        $theme = new Theme(
            backgroundColor: '#fff',
            nodeFillColor: '#eee',
            nodeStrokeColor: '#333',
            textColor: '#111',
            mutedTextColor: '#666',
            accentColors: ['#00f'],
            fontFamily: 'sans-serif',
            fontSize: 14.0,
            spacingUnit: 8.0,
            minNodeWidth: 100.0,
            minNodeHeight: 88.0,
        );

        $svg = (new SvgRenderer())->render((new StateLayoutEngine())->layout($diagram, $theme));

        $this->assertStringContainsString('width="100"', $svg);
        $this->assertStringContainsString('height="88"', $svg);
    }

    public function testTransitionSkippingARankIsDrawnAsACurve(): void
    {
        // A->B->C establishes ranks 0,1,2; the extra A->C jumps a rank and must
        // bow into a cubic curve instead of a straight line. Labels on both a
        // skip edge and an adjacent edge exercise the curved- and straight-edge
        // label placement (the latter via the readable-angle path in LR mode).
        $diagram = (new StateDiagramBuilder())
            ->direction(Direction::LeftToRight)
            ->transition('A', 'B', 'step')
            ->transition('B', 'C')
            ->transition('A', 'C', 'skip')
            ->build();

        $scene = (new StateLayoutEngine())->layout($diagram, Theme::default());

        // Adjacent transitions render as straight LineNodes; the rank-skipping
        // A->C bows into a cubic PathNode, so only the two adjacent edges remain
        // straight.
        $lines = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof LineNode));
        $this->assertCount(2, $lines, 'only the two adjacent transitions should stay straight; A->C must curve');
    }

    public function testUnlabeledSelfLoopIsDrawnAsACurve(): void
    {
        $diagram = (new StateDiagramBuilder())
            ->direction(Direction::LeftToRight)
            ->transition('A', 'B')
            ->transition('A', 'A')
            ->build();

        $scene = (new StateLayoutEngine())->layout($diagram, Theme::default());

        // A->B is the only straight edge; the self-loop adds a cubic curve plus
        // two arrowheads (which are also paths), so one line and three paths.
        $lines = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof LineNode));
        $paths = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof PathNode));
        $this->assertCount(1, $lines);
        $this->assertCount(3, $paths, 'the self-loop curve and its arrow add two paths beyond the A->B arrow');
    }

    public function testPureCycleWithoutASourceStateIsLaidOut(): void
    {
        // Every state has in-degree >= 1, so the rank seed pass finds no source;
        // the fallback pass must still visit and place both states.
        $diagram = (new StateDiagramBuilder())
            ->transition('A', 'B')
            ->transition('B', 'A')
            ->build();

        $scene = (new StateLayoutEngine())->layout($diagram, Theme::default());

        $this->assertGreaterThan(0, \count($scene->nodes));
    }
}
