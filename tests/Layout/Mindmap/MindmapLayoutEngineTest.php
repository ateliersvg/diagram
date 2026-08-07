<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Mindmap;

use Atelier\Diagram\Layout\Mindmap\MindmapLayoutEngine;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MindmapLayoutEngine::class)]
#[CoversClass(MindmapDiagram::class)]
#[CoversClass(MindmapDiagramBuilder::class)]
final class MindmapLayoutEngineTest extends TestCase
{
    public function testRootLabelTextOnAccentFillFollowsTheme(): void
    {
        $theme = Theme::dark();
        $diagram = (new MindmapDiagramBuilder())
            ->root('Atelier', 'root')
            ->child('root', 'Layout')
            ->build();

        $scene = (new MindmapLayoutEngine())->layout($diagram, $theme);

        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $this->assertEmpty(array_filter($texts, static fn (TextNode $node): bool => '#ffffff' === $node->style->fill));
        $roots = array_values(array_filter($texts, static fn (TextNode $node): bool => 'Atelier' === $node->text));
        $this->assertNotEmpty($roots);
        $this->assertSame($theme->backgroundColor, $roots[0]->style->fill);
    }

    public function testRendersMindmapToSvg(): void
    {
        $diagram = (new MindmapDiagramBuilder())
            ->root('Atelier', 'root')
            ->child('root', 'Layout', 'layout')
            ->child('layout', 'Grid')
            ->child('layout', 'Stack')
            ->child('root', 'Diagram', 'diagram')
            ->child('diagram', 'Sequence')
            ->build();

        $svg = (new SvgRenderer())->render((new MindmapLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('Atelier', $svg);
        $this->assertStringContainsString('Sequence', $svg);
        $this->assertStringContainsString('<path', $svg);
    }
}
