<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Er;

use Atelier\Diagram\Er\ErAttribute;
use Atelier\Diagram\Er\ErCardinality;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Er\ErEntity;
use Atelier\Diagram\Er\ErRelationship;
use Atelier\Diagram\Layout\Er\ErLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErLayoutEngine::class)]
#[CoversClass(ErDiagram::class)]
#[CoversClass(ErDiagramBuilder::class)]
#[CoversClass(ErEntity::class)]
#[CoversClass(ErAttribute::class)]
#[CoversClass(ErRelationship::class)]
#[CoversClass(ErCardinality::class)]
final class ErLayoutEngineTest extends TestCase
{
    public function testEntityTitleTextOnAccentHeaderFollowsTheme(): void
    {
        $theme = Theme::dark();
        $diagram = (new ErDiagramBuilder())
            ->attribute('CUSTOMER', 'int', 'id')
            ->build();

        $scene = (new ErLayoutEngine())->layout($diagram, $theme);

        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $this->assertEmpty(array_filter($texts, static fn (TextNode $node): bool => '#ffffff' === $node->style->fill));
        $titles = array_values(array_filter($texts, static fn (TextNode $node): bool => 'CUSTOMER' === $node->text));
        $this->assertNotEmpty($titles);
        $this->assertSame($theme->backgroundColor, $titles[0]->style->fill);
    }

    public function testRendersErDiagramToSvg(): void
    {
        $diagram = (new ErDiagramBuilder())
            ->attribute('CUSTOMER', 'string', 'email')
            ->attribute('ORDER', 'decimal', 'total')
            ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
            ->build();

        $svg = (new SvgRenderer())->render((new ErLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('CUSTOMER', $svg);
        $this->assertStringContainsString('email', $svg);
        $this->assertStringContainsString('ORDER', $svg);
        $this->assertStringContainsString('places', $svg);
        $this->assertStringContainsString('o{', $svg);
    }

    public function testRendersVerticalRelationshipConnections(): void
    {
        $diagram = (new ErDiagramBuilder())
            ->attribute('CUSTOMER', 'string', 'email')
            ->attribute('ORDER', 'decimal', 'total')
            ->attribute('PAYMENT', 'string', 'reference')
            ->relationship('CUSTOMER', '||', 'PAYMENT', 'o{', 'pays')
            ->build();

        $svg = (new SvgRenderer())->render((new ErLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('PAYMENT', $svg);
        $this->assertStringContainsString('pays', $svg);
    }
}
