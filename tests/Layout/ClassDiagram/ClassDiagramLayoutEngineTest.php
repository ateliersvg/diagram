<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\ClassDiagram;

use Atelier\Diagram\ClassDiagram\ClassBox;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
use Atelier\Diagram\ClassDiagram\ClassMember;
use Atelier\Diagram\ClassDiagram\ClassRelation;
use Atelier\Diagram\Layout\ClassDiagram\ClassDiagramLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassDiagramLayoutEngine::class)]
#[CoversClass(ClassDiagram::class)]
#[CoversClass(ClassDiagramBuilder::class)]
#[CoversClass(ClassBox::class)]
#[CoversClass(ClassMember::class)]
#[CoversClass(ClassRelation::class)]
final class ClassDiagramLayoutEngineTest extends TestCase
{
    public function testRendersClassDiagramToSvg(): void
    {
        $diagram = (new ClassDiagramBuilder())
            ->member('User', '+id int')
            ->member('Order', '+total Money')
            ->relation('User', 'Order', 'places')
            ->build();

        $svg = (new SvgRenderer())->render((new ClassDiagramLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('User', $svg);
        $this->assertStringContainsString('+id int', $svg);
        $this->assertStringContainsString('places', $svg);
    }
}
