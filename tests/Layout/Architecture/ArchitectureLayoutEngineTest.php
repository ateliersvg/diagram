<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Architecture;

use Atelier\Diagram\Architecture\ArchitectureDiagramBuilder;
use Atelier\Diagram\Layout\Architecture\ArchitectureLayoutEngine;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchitectureLayoutEngine::class)]
final class ArchitectureLayoutEngineTest extends TestCase
{
    public function testLaysOutGroupsNodesAndRelationships(): void
    {
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

        $scene = (new ArchitectureLayoutEngine())->layout($diagram, Theme::default());

        $this->assertGreaterThan(0.0, $scene->width);
        $this->assertGreaterThan(0.0, $scene->height);
        $this->assertGreaterThanOrEqual(7, \count(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode)));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof PathNode));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Checkout platform' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Frontend app' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'writes' === $node->text));
    }

    public function testCyclesAccentColorsWhenThemeProvidesAShortPalette(): void
    {
        $diagram = (new ArchitectureDiagramBuilder())
            ->group('Platform')
            ->person('Customer', 'Customer', 'Platform')
            ->system('Store', 'Store', 'Platform')
            ->container('Web', 'Web app', 'Platform')
            ->component('Api', 'API', 'Platform')
            ->database('Orders', 'Orders DB', 'Platform')
            ->queue('Events', 'Events', 'Platform')
            ->external('Stripe', 'Stripe', 'Platform')
            ->build();

        $theme = new Theme(
            backgroundColor: '#fff',
            nodeFillColor: '#f8fafc',
            nodeStrokeColor: '#111827',
            textColor: '#111827',
            mutedTextColor: '#6b7280',
            accentColors: ['#2563eb'],
            fontFamily: 'Helvetica, Arial, sans-serif',
            fontSize: 14.0,
            spacingUnit: 8.0,
        );

        $scene = (new ArchitectureLayoutEngine())->layout($diagram, $theme);

        $this->assertGreaterThan(0.0, $scene->width);
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Orders DB' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Events' === $node->text));
    }

    public function testGroupPanelsBadgesAndNodeFillsFollowTheme(): void
    {
        $theme = Theme::dark();
        $diagram = (new ArchitectureDiagramBuilder())
            ->group('Web', 'Web tier')
            ->person('Customer', 'Customer', 'Web')
            ->database('Orders', 'Orders DB', 'Web')
            ->external('Stripe', 'Stripe', 'Web')
            ->build();

        $scene = (new ArchitectureLayoutEngine())->layout($diagram, $theme);

        $rects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode));
        $lightFills = ['#f8fafc', '#eff6ff', '#f0fdf4', '#fff7ed', '#faf5ff', '#ecfeff'];
        $this->assertEmpty(array_filter($rects, static fn (RectNode $node): bool => \in_array($node->style->fill, $lightFills, true)));
        $this->assertNotEmpty(array_filter($rects, static fn (RectNode $node): bool => $theme->nodeFillColor === $node->style->fill));

        $badges = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'PERSON' === $node->text));
        $this->assertNotEmpty($badges);
        $this->assertSame($theme->backgroundColor, $badges[0]->style->fill);
    }

    public function testUngroupedNodesGetSyntheticGroup(): void
    {
        $diagram = (new ArchitectureDiagramBuilder())
            ->group('Web', 'Web tier')
            ->component('App', 'Frontend app', 'Web')
            ->component('Loose', 'Standalone service')
            ->build();

        $scene = (new ArchitectureLayoutEngine())->layout($diagram, Theme::default());

        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Ungrouped' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Standalone service' === $node->text));
    }

    public function testKindBadgeAndNodeLabelUseSeparateVerticalBands(): void
    {
        $diagram = (new ArchitectureDiagramBuilder())
            ->component('App', 'Frontend app')
            ->build();

        $scene = (new ArchitectureLayoutEngine())->layout($diagram, Theme::default());
        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $badge = array_values(array_filter($texts, static fn (TextNode $node): bool => 'COMPONENT' === $node->text))[0];
        $label = array_values(array_filter($texts, static fn (TextNode $node): bool => 'Frontend app' === $node->text))[0];

        $this->assertGreaterThan(22.0, $label->y - $badge->y);
    }
}
