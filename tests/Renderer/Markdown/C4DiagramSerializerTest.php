<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Markdown;

use Atelier\Diagram\C4\C4Boundary;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\C4\C4Element;
use Atelier\Diagram\C4\C4ElementKind;
use Atelier\Diagram\C4\C4Relationship;
use Atelier\Diagram\C4\C4View;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;
use Atelier\Diagram\Renderer\Markdown\C4DiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\MermaidSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(C4DiagramSerializer::class)]
#[CoversClass(MermaidSerializable::class)]
final class C4DiagramSerializerTest extends TestCase
{
    public function testSerializesTechnologyLessDescriptionWithEmptyTechnologySlot(): void
    {
        $diagram = new C4Diagram(
            C4View::Component,
            [new C4Boundary('api', new Label('API'))],
            [
                new C4Element('console', C4ElementKind::Component, new Label('Console'), description: new Label('Admin UI'), boundaryId: 'api'),
                new C4Element('auditor', C4ElementKind::PersonExternal, new Label('Auditor'), technology: new Label('ignored'), description: new Label('Reviews exports')),
            ],
            [new C4Relationship('auditor', 'console', new Label('requests evidence'), new Label('HTTPS'))],
            new Title('Compliance'),
        );

        $this->assertSame(
            <<<'MERMAID'
            C4Component
                title Compliance
                System_Boundary(api, "API") {
                    Component(console, "Console", "", "Admin UI")
                }
                Person_Ext(auditor, "Auditor", "Reviews exports")
                Rel(auditor, console, "requests evidence", "HTTPS")
            
            MERMAID,
            (new C4DiagramSerializer())->serialize($diagram),
        );
    }

    public function testRejectsUnserializableElementId(): void
    {
        $diagram = new C4Diagram(
            C4View::Context,
            [],
            [new C4Element('bad-id', C4ElementKind::System, new Label('App'))],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render C4 diagram to Mermaid: element id "bad-id" is not a supported identifier.');

        (new C4DiagramSerializer())->serialize($diagram);
    }

    public function testRejectsUnserializableText(): void
    {
        $diagram = new C4Diagram(
            C4View::Context,
            [],
            [new C4Element('app', C4ElementKind::System, new Label("App\nName"))],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render C4 diagram to Mermaid: label of element "app" contains unsupported control characters.');

        (new C4DiagramSerializer())->serialize($diagram);
    }
}
