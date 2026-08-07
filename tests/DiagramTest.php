<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\Diagram;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Layout\Architecture\ArchitectureLayoutEngine;
use Atelier\Diagram\Layout\Block\BlockLayoutEngine;
use Atelier\Diagram\Layout\C4\C4LayoutEngine;
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
use Atelier\Diagram\Layout\Venn\VennLayoutEngine;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Parser\Support\ParseResult;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Venn\VennDiagram;
use Atelier\Svg\Dumper\CompactXmlDumper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Happy path per diagram type: the facade output is byte-identical to the
 * explicit engine + renderer composition it hides. Everything else (layout
 * geometry, grammar round-trips) is covered by the dedicated test classes.
 */
#[CoversClass(Diagram::class)]
#[CoversClass(ParseResult::class)]
final class DiagramTest extends TestCase
{
    public function testStateDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::state()
            ->initial('Draft')
            ->transition('Draft', 'Done', 'finish')
            ->final('Done')
            ->build();

        $expected = (new SvgRenderer())->render((new StateLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testClassDiagramFactoryBuildsAndRenders(): void
    {
        $model = Diagram::classDiagram()
            ->class('User')
            ->member('User', '+name: string')
            ->build();

        $this->assertStringContainsString('<svg', Diagram::of($model)->toSvg());
    }

    public function testBlueprintThemeInjectsBackgroundPatternIntoRenderedSvg(): void
    {
        $model = Diagram::state()
            ->initial('Draft')
            ->transition('Draft', 'Done', 'finish')
            ->final('Done')
            ->build();

        $svg = Diagram::of($model)->toSvg(Theme::blueprint());

        $this->assertStringContainsString('id="adi-bg-grid"', $svg);
        $this->assertStringContainsString('fill="url(#adi-bg-grid)"', $svg);
    }

    public function testDefaultThemeLeavesNoBackgroundPatternInRenderedSvg(): void
    {
        $model = Diagram::state()
            ->initial('Draft')
            ->transition('Draft', 'Done', 'finish')
            ->final('Done')
            ->build();

        $this->assertStringNotContainsString('<pattern', Diagram::of($model)->toSvg());
    }

    public function testFromMermaidParsesIndentedStateDiagramHeader(): void
    {
        $diagram = Diagram::fromMermaid("  stateDiagram-v2\n  [*] --> Idle");

        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesBomPrefixedStateDiagramHeader(): void
    {
        $diagram = Diagram::fromMermaid("\xEF\xBB\xBFstateDiagram-v2\n[*] --> Idle");

        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesCarriageReturnOnlyStateDiagramHeader(): void
    {
        $diagram = Diagram::fromMermaid("stateDiagram-v2\r[*] --> Idle\r");

        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesVerticalTabSeparatedStateDiagramHeader(): void
    {
        $diagram = Diagram::fromMermaid("stateDiagram-v2\v[*] --> Idle\v");

        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesFormFeedSeparatedStateDiagramHeader(): void
    {
        $diagram = Diagram::fromMermaid("stateDiagram-v2\f[*] --> Idle\f");

        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
    }

    public function testVennDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::venn()
            ->set('Cats')
            ->set('Dogs')
            ->regionLabel('AB', 'Pets')
            ->build();

        $expected = (new SvgRenderer())->render((new VennLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testGitGraphToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::git()
            ->commit()
            ->branch('feature')
            ->commit()
            ->checkout('main')
            ->merge('feature')
            ->build();

        $expected = (new SvgRenderer())->render((new GitLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testSequenceDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::sequence()
            ->participant('Client')
            ->participant('Server')
            ->message('Client', 'Server', 'Request')
            ->message('Server', 'Client', 'Response')
            ->build();

        $expected = (new SvgRenderer())->render((new SequenceLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testFlowchartToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::flowchart()
            ->node('A', 'Cart')
            ->edge('A', 'B', 'pay')
            ->node('B', 'Payment')
            ->build();

        $expected = (new SvgRenderer())->render((new FlowchartLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testErDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::er()
            ->attribute('CUSTOMER', 'string', 'email')
            ->attribute('ORDER', 'decimal', 'total')
            ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
            ->build();

        $expected = (new SvgRenderer())->render((new ErLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testTimelineToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::timeline()
            ->section('Discovery')
            ->event('Research complete', '2026-01')
            ->build();

        $expected = (new SvgRenderer())->render((new TimelineLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testJourneyToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::journey()
            ->section('Browse')
            ->task('Open product page', 5, ['Customer'])
            ->build();

        $expected = (new SvgRenderer())->render((new JourneyLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testMindmapToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::mindmap()
            ->root('Atelier', 'root')
            ->child('root', 'Layout')
            ->build();

        $expected = (new SvgRenderer())->render((new MindmapLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testRequirementDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::requirement()
            ->requirement('checkout', ['id' => 'REQ-1'])
            ->element('cart', ['type' => 'component'])
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $expected = (new SvgRenderer())->render((new RequirementLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testKanbanToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::kanban()
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser')
            ->build();

        $expected = (new SvgRenderer())->render((new KanbanLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testBlockDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::block()
            ->block('Solver', 'LayoutSolver')
            ->block('Grid', 'Grid')
            ->relationship('Solver', 'Grid', 'solves')
            ->build();

        $expected = (new SvgRenderer())->render((new BlockLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testArchitectureDiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::architecture()
            ->group('Web', 'Web tier')
            ->component('App', 'Frontend app', 'Web')
            ->component('Api', 'Checkout API', 'Web')
            ->relationship('App', 'Api', 'calls')
            ->build();

        $expected = (new SvgRenderer())->render((new ArchitectureLayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testC4DiagramToSvgMatchesEngineAndRenderer(): void
    {
        $model = Diagram::c4()
            ->containerView()
            ->person('buyer', 'Buyer')
            ->boundary('shop', 'Shop Platform')
                ->container('web', 'Web App', 'Symfony')
                ->container('api', 'API', 'PHP')
            ->endBoundary()
            ->relationship('buyer', 'web', 'uses')
            ->relationship('web', 'api', 'calls')
            ->build();

        $expected = (new SvgRenderer())->render((new C4LayoutEngine())->layout($model, Theme::default()));

        $this->assertSame($expected, Diagram::of($model)->toSvg());
    }

    public function testToSvgDocumentRendersTheSameScene(): void
    {
        $diagram = Diagram::of(Diagram::venn()->set('Cats')->set('Dogs')->build());

        $document = $diagram->toSvgDocument();

        $this->assertSame($diagram->toSvg(), (new CompactXmlDumper())->dump($document));
    }

    public function testSaveSvgWritesTheRenderedSvg(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'diagram-test-');
        $this->assertNotFalse($path);

        try {
            $diagram = Diagram::of(Diagram::git()->commit()->commit()->build());

            $this->assertSame($diagram, $diagram->saveSvg($path));
            $this->assertSame($diagram->toSvg(), file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function testSaveSvgThrowsWhenFileCannotBeWritten(): void
    {
        $diagram = Diagram::of(Diagram::git()->commit()->build());
        $unwritablePath = sys_get_temp_dir().'/diagram-test-missing-'.uniqid().'/output.svg';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write SVG to file: '.$unwritablePath);

        $diagram->saveSvg($unwritablePath);
    }

    public function testFromMermaidToMarkdownRoundTrip(): void
    {
        $source = <<<'MERMAID'
            stateDiagram-v2
                Draft --> Review : submit
                Review --> Done
            MERMAID;

        $diagram = Diagram::fromMermaid($source);
        $mermaid = $diagram->toMermaid();

        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
        // Canonical: re-parsing the rendered source is the identity.
        $this->assertSame($mermaid, Diagram::fromMermaid($mermaid)->toMermaid());
        $this->assertSame("```mermaid\n".$mermaid.'```'."\n", $diagram->toMarkdown());
    }

    public function testFromMermaidParsesGitGraphs(): void
    {
        $diagram = Diagram::fromMermaid("gitGraph\n    commit\n");

        $this->assertInstanceOf(GitGraph::class, $diagram->getModel());
    }

    public function testFromMermaidParsesSequenceDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("sequenceDiagram\n    A->>B: Hello\n");

        $this->assertInstanceOf(SequenceDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesFlowcharts(): void
    {
        $diagram = Diagram::fromMermaid("flowchart TD\n    A --> B\n");

        $this->assertInstanceOf(Flowchart::class, $diagram->getModel());
    }

    public function testFromMermaidParsesErDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("erDiagram\nCUSTOMER {\nstring email\n}\n");

        $this->assertInstanceOf(ErDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesTimelineDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("timeline\n    section Discovery\n        Research complete : 2026-01\n");

        $this->assertInstanceOf(TimelineDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesMindmaps(): void
    {
        $diagram = Diagram::fromMermaid("mindmap\n  root((Atelier))\n    Layout\n");

        $this->assertInstanceOf(MindmapDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesRequirementDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("requirementDiagram\nrequirement checkout {\nid: REQ-1\n}\n");

        $this->assertInstanceOf(RequirementDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesKanbanDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("kanban\n    todo [Todo]\n        REQ-1 [Write parser]\n");

        $this->assertInstanceOf(KanbanDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesBlockDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("block\n    block Solver [LayoutSolver]\n");

        $this->assertInstanceOf(BlockDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesArchitectureDiagrams(): void
    {
        $diagram = Diagram::fromMermaid("architecture\ncomponent App [Frontend app]\n");

        $this->assertInstanceOf(ArchitectureDiagram::class, $diagram->getModel());
    }

    public function testFromMermaidParsesC4Diagrams(): void
    {
        $diagram = Diagram::fromMermaid("C4Context\nPerson(user, \"User\")\nSystem(app, \"Application\")\nRel(user, app, \"uses\")\n");

        $this->assertInstanceOf(C4Diagram::class, $diagram->getModel());
    }

    public function testFromMermaidAcceptsParserInputLimits(): void
    {
        try {
            Diagram::fromMermaid("stateDiagram-v2\nIdle --> VeryLongStateName", new ParserInputLimits(maxLineBytes: 20));
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('parser.line_too_long', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getLineNumber());
        }
    }

    public function testTryFromMermaidReturnsSuccessResult(): void
    {
        $result = Diagram::tryFromMermaid("stateDiagram-v2\n[*] --> Idle");
        $diagram = $result->getModel();

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(Diagram::class, $diagram);
        $this->assertInstanceOf(StateDiagram::class, $diagram->getModel());
        $this->assertNull($result->getDiagnostic());
    }

    public function testTryFromMermaidReturnsFailureResultWithoutThrowing(): void
    {
        $result = Diagram::tryFromMermaid("pie\nA : 10");

        $this->assertTrue($result->isFailure());
        $this->assertNull($result->getModel());
        $this->assertSame('parser.unknown_header', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryFromMermaidRejectsWhitespaceOnlyInputAsEmptyInput(): void
    {
        $result = Diagram::tryFromMermaid("\n \t \n");

        $this->assertTrue($result->isFailure());
        $this->assertSame('parser.empty_input', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryFromMermaidRejectsCommentOnlyInputAsEmptyInput(): void
    {
        $result = Diagram::tryFromMermaid("%% comment only\n\n");

        $this->assertTrue($result->isFailure());
        $this->assertSame('parser.empty_input', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryFromMermaidRejectsBomPrefixedCommentOnlyInputAsEmptyInput(): void
    {
        $result = Diagram::tryFromMermaid("\xEF\xBB\xBF%% comment only\n\n");

        $this->assertTrue($result->isFailure());
        $this->assertSame('parser.empty_input', $result->getDiagnostic()?->code);
        $this->assertSame(1, $result->getException()?->getLineNumber());
    }

    public function testTryFromMermaidAcceptsParserInputLimits(): void
    {
        $result = Diagram::tryFromMermaid("stateDiagram-v2\nIdle --> VeryLongStateName", new ParserInputLimits(maxLineBytes: 20));

        $this->assertTrue($result->isFailure());
        $this->assertSame('parser.line_too_long', $result->getDiagnostic()?->code);
    }

    public function testVennDiagramCannotBeRenderedToMarkdown(): void
    {
        $diagram = Diagram::of(Diagram::venn()->set('Cats')->set('Dogs')->build());

        $this->assertInstanceOf(VennDiagram::class, $diagram->getModel());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venn diagrams cannot be rendered to Mermaid');

        $diagram->toMarkdown();
    }
}
