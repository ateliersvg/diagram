<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Block;

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\Block\BlockGroup;
use Atelier\Diagram\Block\BlockNode;
use Atelier\Diagram\Block\BlockRelationship;
use Atelier\Diagram\Layout\Block\BlockLayoutEngine;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockLayoutEngine::class)]
#[CoversClass(BlockDiagram::class)]
#[CoversClass(BlockDiagramBuilder::class)]
#[CoversClass(BlockNode::class)]
#[CoversClass(BlockGroup::class)]
#[CoversClass(BlockRelationship::class)]
final class BlockLayoutEngineTest extends TestCase
{
    public function testClusterFillFollowsTheme(): void
    {
        $theme = Theme::dark();
        $diagram = (new BlockDiagramBuilder())
            ->block('Solver', 'LayoutSolver')
            ->beginGroup('Primitives', 'Primitives')
                ->block('Rect', 'Rect')
            ->endGroup()
            ->relationship('Solver', 'Rect', 'places')
            ->build();

        $scene = (new BlockLayoutEngine())->layout($diagram, $theme);

        $clusters = array_values(array_filter(
            $scene->nodes,
            static fn ($node): bool => $node instanceof RectNode && LineStyle::Dashed === $node->style->lineStyle,
        ));
        $this->assertNotEmpty($clusters);
        foreach ($clusters as $cluster) {
            $this->assertSame($theme->nodeFillColor, $cluster->style->fill);
        }
    }

    public function testRendersBlockDiagramToSvg(): void
    {
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

        $svg = (new SvgRenderer())->render((new BlockLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('Layout kernel', $svg);
        $this->assertStringContainsString('LayoutSolver', $svg);
        $this->assertStringContainsString('Primitives', $svg);
        $this->assertStringContainsString('solves', $svg);
    }

    public function testRootBlockIsCentredBetweenItsFirstRowTargets(): void
    {
        require_once dirname(__DIR__, 3).'/examples/block-diagram.php';
        $scene = (new BlockLayoutEngine())->layout(buildBlockDiagram(), Theme::default());
        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $positions = [];
        foreach ($texts as $text) {
            $positions[$text->text] = $text->x;
        }

        $this->assertLessThan($positions['LayoutSolver'], $positions['Grid']);
        $this->assertGreaterThan($positions['LayoutSolver'], $positions['TextBlock']);
    }
}
