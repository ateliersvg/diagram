<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\C4;

use Atelier\Diagram\C4\C4DiagramBuilder;
use Atelier\Diagram\Layout\C4\C4LayoutEngine;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(C4LayoutEngine::class)]
final class C4LayoutEngineTest extends TestCase
{
    public function testLaysOutBoundariesExternalElementsStereotypesAndRelationshipLabels(): void
    {
        $diagram = (new C4DiagramBuilder())
            ->containerView()
            ->title('Shop platform')
            ->person('buyer', 'Buyer', 'Places orders')
            ->externalPerson('auditor', 'Auditor')
            ->system('shop', 'Shop')
            ->externalSystem('stripe', 'Stripe')
            ->externalContainer('cache', 'Cache', 'Redis')
            ->externalComponent('legacy', 'Legacy adapter', 'PHP')
            ->boundary('platform', 'Shop Platform')
                ->container('web', 'Web App', 'Symfony')
                ->database('db', 'Orders DB', 'PostgreSQL')
                ->component('api', 'API', 'PHP')
                ->componentDatabase('projection', 'Projection DB', 'SQLite')
            ->endBoundary()
            ->relationship('buyer', 'web', 'uses')
            ->relationship('web', 'api', 'submits checkout', 'HTTPS')
            ->relationship('api', 'db', 'writes')
            ->relationship('api', 'stripe', 'charges card')
            ->build();

        $scene = (new C4LayoutEngine())->layout($diagram, Theme::default());

        $this->assertGreaterThan(0.0, $scene->width);
        $this->assertGreaterThan(0.0, $scene->height);
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof PathNode));
        $this->assertTextNodeExists($scene->nodes, 'System Boundary: Shop Platform');
        $this->assertTextNodeExists($scene->nodes, '[Person]');
        $this->assertTextNodeExists($scene->nodes, '[External Person]');
        $this->assertTextNodeExists($scene->nodes, '[Software System]');
        $this->assertTextNodeExists($scene->nodes, '[External System]');
        $this->assertTextNodeExists($scene->nodes, '[External Container: Redis]');
        $this->assertTextNodeExists($scene->nodes, '[External Component: PHP]');
        $this->assertTextNodeExists($scene->nodes, '[Container: Symfony]');
        $this->assertTextNodeExists($scene->nodes, '[Database: PostgreSQL]');
        $this->assertTextNodeExists($scene->nodes, '[Component: PHP]');
        $this->assertTextNodeExists($scene->nodes, '[Component DB: SQLite]');
        $this->assertTextNodeExists($scene->nodes, 'submits checkout /');
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode && LineStyle::Dashed === $node->style->lineStyle));
    }

    public function testKeepsCompactSpacingWhenThereAreNoRelationships(): void
    {
        $diagram = (new C4DiagramBuilder())
            ->contextView()
            ->system('app', 'Application')
            ->build();

        $scene = (new C4LayoutEngine())->layout($diagram, Theme::default());

        $this->assertSame(560.0, $scene->width);
        $this->assertTextNodeExists($scene->nodes, '[Software System]');
        $this->assertTextNodeExists($scene->nodes, 'Application');
    }

    public function testBoundaryAndElementColorsFollowTheme(): void
    {
        $theme = Theme::dark();
        $accent = $theme->accentColors[0];
        $diagram = (new C4DiagramBuilder())
            ->containerView()
            ->person('buyer', 'Buyer')
            ->externalSystem('stripe', 'Stripe')
            ->boundary('shop', 'Shop')
                ->container('web', 'Web App', 'Symfony')
                ->database('db', 'Orders DB', 'PostgreSQL')
            ->endBoundary()
            ->relationship('buyer', 'web', 'uses')
            ->build();

        $scene = (new C4LayoutEngine())->layout($diagram, $theme);

        $rects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode));
        $this->assertEmpty(array_filter($rects, static fn (RectNode $node): bool => '#0369a1' === $node->style->stroke));
        $lightFills = ['#f8fafc', '#dbeafe', '#e0f2fe', '#eff6ff', '#fef9c3'];
        $this->assertEmpty(array_filter($rects, static fn (RectNode $node): bool => \in_array($node->style->fill, $lightFills, true)));
        $this->assertNotEmpty(array_filter($rects, static fn (RectNode $node): bool => $accent === $node->style->stroke && LineStyle::Dashed === $node->style->lineStyle));
        $this->assertNotEmpty(array_filter($rects, static fn (RectNode $node): bool => $theme->nodeFillColor === $node->style->fill));
    }

    /**
     * @param list<object> $nodes
     */
    private function assertTextNodeExists(array $nodes, string $text): void
    {
        $this->assertNotEmpty(
            array_filter($nodes, static fn ($node): bool => $node instanceof TextNode && $text === $node->text),
            \sprintf('Expected text node "%s".', $text),
        );
    }
}
