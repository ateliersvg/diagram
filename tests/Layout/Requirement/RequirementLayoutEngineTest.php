<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Requirement;

use Atelier\Diagram\Layout\Requirement\RequirementLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementDiagramBuilder;
use Atelier\Diagram\Requirement\RequirementNode;
use Atelier\Diagram\Requirement\RequirementNodeKind;
use Atelier\Diagram\Requirement\RequirementRelationship;
use Atelier\Diagram\Requirement\RequirementRelationshipKind;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequirementLayoutEngine::class)]
#[CoversClass(RequirementDiagram::class)]
#[CoversClass(RequirementDiagramBuilder::class)]
#[CoversClass(RequirementNode::class)]
#[CoversClass(RequirementNodeKind::class)]
#[CoversClass(RequirementRelationship::class)]
#[CoversClass(RequirementRelationshipKind::class)]
final class RequirementLayoutEngineTest extends TestCase
{
    public function testHeaderTextOnAccentBandFollowsTheme(): void
    {
        $theme = Theme::dark();
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout', [
                'id' => 'REQ-1',
                'text' => 'Customer can checkout',
            ])
            ->element('cart', ['type' => 'component'])
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $scene = (new RequirementLayoutEngine())->layout($diagram, $theme);

        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $this->assertEmpty(array_filter($texts, static fn (TextNode $node): bool => '#ffffff' === $node->style->fill));
        $this->assertNotEmpty(array_filter($texts, static fn (TextNode $node): bool => $theme->backgroundColor === $node->style->fill));
    }

    public function testRendersRequirementDiagramToSvg(): void
    {
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout', ['id' => 'REQ-1', 'risk' => 'medium'])
            ->element('cart', ['type' => 'component'])
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $svg = (new SvgRenderer())->render((new RequirementLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('checkout', $svg);
        $this->assertStringContainsString('REQ-1', $svg);
        $this->assertStringContainsString('cart', $svg);
        $this->assertStringContainsString('satisfies', $svg);
    }

    public function testRequirementOnlyDiagramHandlesEmptyElementGroupAndStackedNodes(): void
    {
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('login', ['id' => 'REQ-1'])
            ->requirement('logout', ['id' => 'REQ-2'])
            ->build();

        $scene = (new RequirementLayoutEngine())->layout($diagram, Theme::default());

        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'login' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'logout' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'requirements' === $node->text));
    }

    public function testAlignedSingleNodesProduceStraightRelationshipLine(): void
    {
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout', ['id' => 'REQ-1'])
            ->element('cart', ['type' => 'component'])
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $scene = (new RequirementLayoutEngine())->layout($diagram, Theme::default());

        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof LineNode));
    }

    public function testNodesWithoutFieldsRenderTheirKind(): void
    {
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->element('cart')
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $scene = (new RequirementLayoutEngine())->layout($diagram, Theme::default());

        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'requirement' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'element' === $node->text));
    }
}
