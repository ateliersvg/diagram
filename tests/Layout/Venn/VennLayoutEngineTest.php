<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Venn;

use Atelier\Diagram\Layout\Venn\VennLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Venn\VennDiagram;
use Atelier\Diagram\Venn\VennDiagramBuilder;
use Atelier\Diagram\Venn\VennSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end snapshot: builder -> layout -> SvgRenderer against the frozen
 * reference artifacts reviewed in examples/output/. Builder validation and
 * layout edge cases are deliberately untested until part 3.3.
 */
#[CoversClass(VennLayoutEngine::class)]
#[CoversClass(VennDiagram::class)]
#[CoversClass(VennDiagramBuilder::class)]
#[CoversClass(VennSet::class)]
final class VennLayoutEngineTest extends TestCase
{
    use ExampleFixtures;

    public function testLayoutMatchesFrozenSnapshots(): void
    {
        self::loadExample('venn-diagrams.php');

        $engine = new VennLayoutEngine();
        $renderer = new SvgRenderer();
        $theme = Theme::default();

        foreach (['venn-2.svg' => buildVenn2(), 'venn-3.svg' => buildVenn3()] as $file => $diagram) {
            $svg = $renderer->render($engine->layout($diagram, $theme));

            $snapshot = file_get_contents(__DIR__.'/__snapshots__/'.$file);
            $this->assertNotFalse($snapshot);
            $this->assertSame(trim($snapshot), trim($svg), $file);
        }
    }

    public function testTargetCanvasPaddingStrokeAndWrappedLabels(): void
    {
        $diagram = (new VennDiagramBuilder())
            ->set('Long Alpha Label')
            ->set('Long Beta Label')
            ->regionLabel('AB', 'Shared capability label')
            ->targetSize(200, 400)
            ->paddingPercent(4)
            ->innerPaddingPercent(4)
            ->circleStrokeWidth(10)
            ->build();

        $scene = (new VennLayoutEngine())->layout($diagram, Theme::default());

        $this->assertSame(200.0, $scene->width);
        $this->assertSame(400.0, $scene->height);

        $circles = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof CircleNode));
        $this->assertCount(2, $circles);
        foreach ($circles as $circle) {
            \assert($circle instanceof CircleNode);
            $this->assertSame(10.0, $circle->style->strokeWidth);
            $this->assertGreaterThanOrEqual(8.0, $circle->cx - $circle->r);
            $this->assertLessThanOrEqual(192.0, $circle->cx + $circle->r);
            $this->assertGreaterThanOrEqual(16.0, $circle->cy - $circle->r);
            $this->assertLessThanOrEqual(384.0, $circle->cy + $circle->r);
        }

        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $this->assertGreaterThan(3, \count($texts));
        $this->assertNotEmpty(array_filter($texts, static fn (TextNode $node): bool => str_contains($node->text, 'Shared') || str_contains($node->text, 'capability')));
    }
}
