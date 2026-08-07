<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Flow;

use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Layout\Flow\FlowchartLayoutEngine;
use Atelier\Diagram\Layout\Support\ConnectionLabelArtist;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Geometry\Rect;
use Atelier\Layout\Geometry\RectIndex;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextBlockMetrics;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Text\TextMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowchartLayoutEngine::class)]
#[CoversClass(ConnectionLabelArtist::class)]
final class FlowchartLayoutEngineTest extends TestCase
{
    use ExampleFixtures;

    public function testRendersNodesAndLabeledEdges(): void
    {
        self::loadExample('flowchart-workflow.php');

        $svg = (new SvgRenderer())->render((new FlowchartLayoutEngine())->layout(buildFlowchartWorkflow(), Theme::default()));

        $snapshot = file_get_contents(__DIR__.'/__snapshots__/flowchart-workflow.svg');
        $this->assertNotFalse($snapshot);
        $this->assertSame(trim($snapshot), trim($svg));
    }

    public function testAvoidsNodeOverlapForLargeEdgeLabels(): void
    {
        $engine = new FlowchartLayoutEngine(new class implements TextMeasurerInterface {
            public function measureLine(string $text, float $fontSize, FontWeight $weight = FontWeight::Normal): TextMetrics
            {
                return 'edge label with enough width and height to collide' === $text
                    ? new TextMetrics(80.0, 30.0, 20.0)
                    : new TextMetrics(20.0, 20.0, 14.0);
            }

            public function wrap(string $text, float $maxWidth, float $fontSize, float $lineHeight = 1.2, bool $breakWords = false, FontWeight $weight = FontWeight::Normal): TextBlockMetrics
            {
                $line = $this->measureLine($text, $fontSize, $weight);

                return new TextBlockMetrics([$text], $line->width, $line->height, $line->ascent, $line->ascent);
            }
        });

        $flowchart = (new FlowchartBuilder())
            ->direction(Direction::TopToBottom)
            ->node('A', 'Alpha')
            ->node('B', 'Beta')
            ->edge('A', 'B', 'edge label with enough width and height to collide')
            ->build();

        $scene = $engine->layout($flowchart, Theme::default());

        $rects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode));
        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));

        $this->assertCount(3, $rects);
        $this->assertCount(3, $texts);

        $labelRects = array_values(array_filter($rects, static fn (RectNode $rect): bool => 80.0 < $rect->width));
        $nodeRects = array_values(array_filter($rects, static fn (RectNode $rect): bool => 80.0 >= $rect->width));

        $this->assertCount(1, $labelRects);
        $this->assertCount(2, $nodeRects);

        $labelRect = new Rect($labelRects[0]->x, $labelRects[0]->y, $labelRects[0]->width, $labelRects[0]->height);
        $this->assertTrue(RectIndex::from([
            'A' => new Rect($nodeRects[0]->x, $nodeRects[0]->y, $nodeRects[0]->width, $nodeRects[0]->height),
            'B' => new Rect($nodeRects[1]->x, $nodeRects[1]->y, $nodeRects[1]->width, $nodeRects[1]->height),
        ])->isFree($labelRect));
    }

    public function testThemeCanSetMinimumFlowchartNodeSize(): void
    {
        $flowchart = (new FlowchartBuilder())
            ->direction(Direction::LeftToRight)
            ->node('A', 'A')
            ->node('B', 'B')
            ->edge('A', 'B', 'next')
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

        $svg = (new SvgRenderer())->render((new FlowchartLayoutEngine())->layout($flowchart, $theme));

        $this->assertStringContainsString('width="100"', $svg);
        $this->assertStringContainsString('height="88"', $svg);
    }

    public function testReroutesAStraightEdgeAroundABlockingNodeLeftToRight(): void
    {
        // A->B->C lays out colinearly across three left-to-right ranks, so the
        // extra A->C edge runs straight through B and must detour into a bent
        // path instead of a straight line.
        $flowchart = (new FlowchartBuilder())
            ->direction(Direction::LeftToRight)
            ->node('A', 'A')
            ->node('B', 'B')
            ->node('C', 'C')
            ->edge('A', 'B')
            ->edge('B', 'C')
            ->edge('A', 'C')
            ->build();

        $scene = (new FlowchartLayoutEngine())->layout($flowchart, Theme::default());

        // Edges render as LineNode when straight, PathNode when bent (arrowheads
        // are always paths). Two adjacent edges stay straight; the blocked A->C
        // detours, so exactly two straight lines remain.
        $lines = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof LineNode));
        $this->assertCount(2, $lines, 'A->C blocked by B should detour to a path, leaving two straight edges');
    }

    public function testConnectsADiagonalEdgeAsAPath(): void
    {
        // A fans out to two siblings sharing rank 1; at least one branch is not
        // axis-aligned with A, so its connection is non-straight and drawn as a path.
        $flowchart = (new FlowchartBuilder())
            ->direction(Direction::LeftToRight)
            ->node('A', 'A')
            ->node('B', 'B')
            ->node('C', 'C')
            ->edge('A', 'B')
            ->edge('A', 'C')
            ->build();

        $scene = (new FlowchartLayoutEngine())->layout($flowchart, Theme::default());

        // At least one branch is not axis-aligned with A, so fewer than two of
        // the two edges stay straight.
        $lines = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof LineNode));
        $this->assertLessThan(2, \count($lines), 'a diagonal branch edge should render as a path, not a straight line');
    }

    public function testSelfLoopEdgeDoesNotAffectRanking(): void
    {
        $flowchart = (new FlowchartBuilder())
            ->direction(Direction::TopToBottom)
            ->node('A', 'A')
            ->node('B', 'B')
            ->edge('A', 'B')
            ->edge('A', 'A')
            ->build();

        $scene = (new FlowchartLayoutEngine())->layout($flowchart, Theme::default());

        $rects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode));
        $this->assertCount(2, $rects, 'the self-loop must not create or displace a rank');
    }
}
