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
use Atelier\Diagram\Scene\RectNode;
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

    public function testLongAttributeNamesAndTypesReceiveSeparateColumns(): void
    {
        $diagram = (new ErDiagramBuilder())
            ->attribute('SHIPMENT', 'string', 'trackingNumber')
            ->build();

        $scene = (new ErLayoutEngine())->layout($diagram, Theme::default());
        $boxes = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode && null !== $node->style->stroke));

        $this->assertNotEmpty($boxes);
        $this->assertGreaterThan(200.0, $boxes[0]->width);
    }

    public function testRelationshipHalosDoNotReuseTheSameFrame(): void
    {
        require_once dirname(__DIR__, 3).'/examples/er-diagram.php';
        $theme = Theme::default();
        $scene = (new ErLayoutEngine())->layout(buildErDiagram(), $theme);
        $halos = array_values(array_filter(
            $scene->nodes,
            static fn ($node): bool => $node instanceof RectNode
                && $theme->backgroundColor === $node->style->fill
                && null === $node->style->stroke
                && $node->width > 35.0,
        ));

        $origins = array_map(static fn (RectNode $node): string => $node->x.':'.$node->y, $halos);
        $this->assertSame($origins, array_values(array_unique($origins)));
        foreach ($halos as $index => $halo) {
            foreach (array_slice($halos, $index + 1) as $other) {
                $intersects = $halo->x < $other->x + $other->width
                    && $halo->x + $halo->width > $other->x
                    && $halo->y < $other->y + $other->height
                    && $halo->y + $halo->height > $other->y;
                $this->assertFalse($intersects, 'Relationship label halos must not overlap.');
            }
        }
    }
}
