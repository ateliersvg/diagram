<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Markdown;

use Atelier\Diagram\Architecture\ArchitectureDiagramBuilder;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Journey\JourneyDiagramBuilder;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
use Atelier\Diagram\Layout\Architecture\ArchitectureLayoutEngine;
use Atelier\Diagram\Layout\Block\BlockLayoutEngine;
use Atelier\Diagram\Layout\ClassDiagram\ClassDiagramLayoutEngine;
use Atelier\Diagram\Layout\Er\ErLayoutEngine;
use Atelier\Diagram\Layout\Flow\FlowchartLayoutEngine;
use Atelier\Diagram\Layout\Git\GitLayoutEngine;
use Atelier\Diagram\Layout\Journey\JourneyLayoutEngine;
use Atelier\Diagram\Layout\Kanban\KanbanLayoutEngine;
use Atelier\Diagram\Layout\Mindmap\MindmapLayoutEngine;
use Atelier\Diagram\Layout\Requirement\RequirementLayoutEngine;
use Atelier\Diagram\Layout\Sequence\SequenceLayoutEngine;
use Atelier\Diagram\Layout\State\StateLayoutEngine;
use Atelier\Diagram\Layout\Timeline\TimelineLayoutEngine;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Renderer\Markdown\ArchitectureDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\BlockDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\ClassDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\ErDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\FlowchartSerializer;
use Atelier\Diagram\Renderer\Markdown\GitGraphSerializer;
use Atelier\Diagram\Renderer\Markdown\JourneyDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\KanbanDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;
use Atelier\Diagram\Renderer\Markdown\MermaidSerializer;
use Atelier\Diagram\Renderer\Markdown\MermaidSerializerRegistry;
use Atelier\Diagram\Renderer\Markdown\MindmapDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\RequirementDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\SequenceDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\StateDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\TimelineDiagramSerializer;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Requirement\RequirementDiagramBuilder;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;
use Atelier\Diagram\Venn\VennDiagramBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip proofs for the Mermaid serialization (section 3.1): rendering
 * is canonical (render → parse → render is the identity) and lossless for
 * layout (the re-parsed model renders byte-identical SVG). Each grammar
 * round-trips its full reference diagram from part 2, exercising the whole
 * supported subset; renderer and parsers are tested together, no fixtures.
 */
#[CoversClass(MarkdownRenderer::class)]
#[CoversClass(MermaidSerializerRegistry::class)]
#[CoversClass(MermaidSerializer::class)]
#[CoversClass(StateDiagramSerializer::class)]
#[CoversClass(GitGraphSerializer::class)]
#[CoversClass(SequenceDiagramSerializer::class)]
#[CoversClass(FlowchartSerializer::class)]
#[CoversClass(ClassDiagramSerializer::class)]
#[CoversClass(ErDiagramSerializer::class)]
#[CoversClass(TimelineDiagramSerializer::class)]
#[CoversClass(JourneyDiagramSerializer::class)]
#[CoversClass(MindmapDiagramSerializer::class)]
#[CoversClass(RequirementDiagramSerializer::class)]
#[CoversClass(KanbanDiagramSerializer::class)]
#[CoversClass(BlockDiagramSerializer::class)]
#[CoversClass(ArchitectureDiagramSerializer::class)]
final class MarkdownRendererTest extends TestCase
{
    use ExampleFixtures;

    public function testStateDiagramRoundTrip(): void
    {
        self::loadExample('state-machine.php');

        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = \buildStateMachineDiagram();

        // Canonical: a second parse/render cycle is the identity.
        $mermaid = $renderer->renderMermaid($parser->parse(\stateMachineMermaid()));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        // Lossless: the re-parsed model renders byte-identical SVG.
        $engine = new StateLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );

        $this->assertSame("```mermaid\n".$renderer->renderMermaid($diagram).'```'."\n", $renderer->render($diagram));
    }

    public function testGitGraphRoundTrip(): void
    {
        self::loadExample('git-history.php');

        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $graph = \buildGitHistory();

        // Canonical: a second parse/render cycle is the identity.
        $mermaid = $renderer->renderMermaid($parser->parse(\gitHistoryMermaid()));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        // Lossless: the re-parsed graph (explicit ids, replayed branch /
        // checkout / merge operations, regenerated auto ids) renders
        // byte-identical SVG.
        $engine = new GitLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($graph, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($graph)), $theme)),
        );

        $this->assertSame("```mermaid\n".$renderer->renderMermaid($graph).'```'."\n", $renderer->render($graph));
    }

    public function testSequenceDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new SequenceDiagramBuilder())
            ->title('Checkout')
            ->participant('User', 'Customer')
            ->participant('Api', 'API')
            ->message('User', 'Api', 'Pay')
            ->message('Api', 'User', 'Receipt', MessageArrow::Dashed)
            ->message('Api', 'Api', 'Validate')
            ->message('User', 'Api', 'Manual review')
            ->block(SequenceBlockKind::Loop, 'retry', 0, 1)
            ->block(SequenceBlockKind::Alt, 'fallback', 2, 3, [
                new SequenceBlockBranch('fallback', 2, 2),
                new SequenceBlockBranch('manual', 3, 3),
            ])
            ->activate('Api')
            ->message('Api', 'User', 'Done')
            ->deactivate('Api')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new SequenceLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );

        $this->assertSame("```mermaid\n".$renderer->renderMermaid($diagram).'```'."\n", $renderer->render($diagram));
    }

    public function testFlowchartRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $flowchart = (new FlowchartBuilder())
            ->node('A', 'Cart')
            ->edge('A', 'B', 'pay')
            ->node('B', 'Payment')
            ->subgraph('payment', 'Payment', ['B'], 'checkout', 1)
            ->subgraph('checkout', 'Checkout', ['A', 'B'])
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($flowchart)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new FlowchartLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($flowchart, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($flowchart)), $theme)),
        );
    }

    public function testClassDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new ClassDiagramBuilder())
            ->member('User', '+id int')
            ->member('Order', '+total Money')
            ->relation('User', 'Order', 'places')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new ClassDiagramLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testErDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new ErDiagramBuilder())
            ->attribute('CUSTOMER', 'string', 'email')
            ->attribute('ORDER', 'decimal', 'total')
            ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new ErLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testTimelineDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new TimelineDiagramBuilder())
            ->title('Product launch')
            ->section('Discovery')
            ->event('Research complete', '2026-01')
            ->event('Prototype review', '2026-02')
            ->section('Build')
            ->event('Public launch', '2026-06')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new TimelineLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testJourneyDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new JourneyDiagramBuilder())
            ->title('Checkout experience')
            ->section('Browse')
            ->task('Open product page', 5, ['Customer'])
            ->task('Add to cart', 4, ['Customer'])
            ->section('Payment')
            ->task('Enter card', 3, ['Customer', 'PSP'])
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new JourneyLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testMindmapRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new MindmapDiagramBuilder())
            ->root('Atelier', 'root')
            ->child('root', 'Layout', 'layout')
            ->child('layout', 'Grid')
            ->child('layout', 'Stack')
            ->child('root', 'Diagram', 'diagram')
            ->child('diagram', 'Sequence')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new MindmapLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testRequirementDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout', ['id' => 'REQ-1', 'risk' => 'medium'])
            ->element('cart', ['type' => 'component'])
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new RequirementLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testKanbanDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new KanbanDiagramBuilder())
            ->title('Delivery board')
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser')
            ->card('todo', 'UI-2', 'Review SVG output')
            ->column('done', 'Done')
            ->card('done', 'CORE-1', 'Scene renderer')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new KanbanLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testBlockDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new BlockDiagramBuilder())
            ->title('Layout kernel')
            ->block('Solver', 'LayoutSolver')
            ->block('Grid', 'Grid')
            ->beginGroup('Primitives', 'Primitives')
                ->block('Rect', 'Rect')
            ->endGroup()
            ->relationship('Solver', 'Grid', 'solves')
            ->relationship('Grid', 'Rect', 'places')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new BlockLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testArchitectureDiagramRoundTrip(): void
    {
        $renderer = new MarkdownRenderer();
        $parser = new MermaidParser();
        $diagram = (new ArchitectureDiagramBuilder())
            ->title('Checkout platform')
            ->group('Web', 'Web tier')
            ->component('App', 'Frontend app', 'Web')
            ->component('Api', 'Checkout API', 'Web')
            ->group('Data', 'Data tier')
            ->database('Orders', 'Orders DB', 'Data')
            ->relationship('App', 'Api', 'calls')
            ->relationship('Api', 'Orders', 'writes')
            ->build();

        $mermaid = $renderer->renderMermaid($parser->parse($renderer->renderMermaid($diagram)));
        $this->assertSame($mermaid, $renderer->renderMermaid($parser->parse($mermaid)));

        $engine = new ArchitectureLayoutEngine();
        $svg = new SvgRenderer();
        $theme = Theme::default();
        $this->assertSame(
            $svg->render($engine->layout($diagram, $theme)),
            $svg->render($engine->layout($parser->parse($renderer->renderMermaid($diagram)), $theme)),
        );
    }

    public function testVennDiagramIsRejected(): void
    {
        $venn = (new VennDiagramBuilder())->set('Cats')->set('Dogs')->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venn diagrams cannot be rendered to Mermaid');

        (new MarkdownRenderer())->renderMermaid($venn);
    }
}
