<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\C4;

use Atelier\Diagram\C4\C4Boundary;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\C4\C4DiagramBuilder;
use Atelier\Diagram\C4\C4Element;
use Atelier\Diagram\C4\C4ElementKind;
use Atelier\Diagram\C4\C4Relationship;
use Atelier\Diagram\C4\C4View;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(C4DiagramBuilder::class)]
#[CoversClass(C4Diagram::class)]
#[CoversClass(C4Boundary::class)]
#[CoversClass(C4Element::class)]
#[CoversClass(C4Relationship::class)]
#[CoversClass(C4ElementKind::class)]
#[CoversClass(C4View::class)]
final class C4DiagramBuilderTest extends TestCase
{
    public function testBuildsBoundariesElementsAndRelationshipsInOrder(): void
    {
        $diagram = (new C4DiagramBuilder())
            ->containerView()
            ->title('Shop platform')
            ->person('buyer', 'Buyer')
            ->externalSystem('stripe', 'Stripe')
            ->boundary('shop', 'Shop Platform')
                ->container('web', 'Web App', 'Symfony')
                ->database('db', 'Orders DB', 'PostgreSQL')
            ->endBoundary()
            ->relationship('buyer', 'web', 'uses')
            ->relationship('web', 'db', 'writes', 'SQL')
            ->build();

        $this->assertSame(C4View::Container, $diagram->view);
        $this->assertSame('Shop platform', $diagram->title?->text);
        $this->assertSame('shop', $diagram->boundaries[0]->id);
        $this->assertSame('buyer', $diagram->elements[0]->id);
        $this->assertSame(C4ElementKind::SystemExternal, $diagram->elements[1]->kind);
        $this->assertSame('shop', $diagram->elements[2]->boundaryId);
        $this->assertSame('SQL', $diagram->relationships[1]->technology?->text);
    }

    public function testBuildsEveryConvenienceKind(): void
    {
        $diagram = (new C4DiagramBuilder())
            ->componentView()
            ->person('Person')
            ->externalPerson('PersonExt')
            ->system('System')
            ->externalSystem('SystemExt')
            ->container('Container')
            ->externalContainer('ContainerExt')
            ->database('Db')
            ->component('Component')
            ->externalComponent('ComponentExt')
            ->componentDatabase('ComponentDb')
            ->build();

        $this->assertSame(
            [
                C4ElementKind::Person,
                C4ElementKind::PersonExternal,
                C4ElementKind::System,
                C4ElementKind::SystemExternal,
                C4ElementKind::Container,
                C4ElementKind::ContainerExternal,
                C4ElementKind::ContainerDatabase,
                C4ElementKind::Component,
                C4ElementKind::ComponentExternal,
                C4ElementKind::ComponentDatabase,
            ],
            array_map(static fn (C4Element $element): C4ElementKind => $element->kind, $diagram->elements),
        );
        $this->assertSame(C4View::Component, $diagram->view);
    }

    public function testContextViewIsTheDefaultAndCanBeSetExplicitly(): void
    {
        $diagram = (new C4DiagramBuilder())
            ->componentView()
            ->contextView()
            ->system('App')
            ->build();

        $this->assertSame(C4View::Context, $diagram->view);
    }

    public function testElementKindMapsEverySupportedMacro(): void
    {
        foreach (C4ElementKind::cases() as $kind) {
            $this->assertSame($kind, C4ElementKind::fromMacro($kind->macro()));
        }
    }

    public function testElementKindRejectsUnsupportedMacro(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported C4 element macro "Deployment_Node"');

        C4ElementKind::fromMacro('Deployment_Node');
    }

    public function testRejectsDuplicateElements(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate C4 element id "api"');

        (new C4DiagramBuilder())
            ->container('api')
            ->container('api');
    }

    public function testRejectsUnknownRelationshipEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown element "api"');

        (new C4DiagramBuilder())
            ->person('buyer')
            ->relationship('buyer', 'api', 'uses');
    }

    public function testRejectsUnknownBoundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown boundary "shop"');

        (new C4DiagramBuilder())->container('api', boundaryId: 'shop');
    }

    public function testRejectsEndingBoundaryBeforeStartingOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot end a C4 boundary before starting one.');

        (new C4DiagramBuilder())->endBoundary();
    }

    public function testRejectsInvalidBuilderIdentifierAndEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 element id "bad-id" is not a supported identifier.');

        (new C4DiagramBuilder())->container('bad-id');
    }

    public function testRejectsEmptyBuilderText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 element "api" label must be non-empty.');

        (new C4DiagramBuilder())->container('api', '   ');
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 diagram must contain at least one element');

        (new C4DiagramBuilder())->build();
    }

    public function testDiagramRejectsRelationshipWithUnknownElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 relationship references unknown element "ghost"');

        new C4Diagram(
            C4View::Context,
            [],
            [new C4Element('api', C4ElementKind::System, new Label('API'))],
            [new C4Relationship('ghost', 'api', new Label('calls'))],
        );
    }

    public function testDiagramRejectsDuplicateBoundaryAndElementIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate C4 boundary id "shop"');

        new C4Diagram(
            C4View::Context,
            [
                new C4Boundary('shop', new Label('Shop')),
                new C4Boundary('shop', new Label('Duplicate')),
            ],
            [new C4Element('api', C4ElementKind::System, new Label('API'))],
        );
    }

    public function testDiagramRejectsDuplicateElementIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate C4 element id "api"');

        new C4Diagram(
            C4View::Context,
            [],
            [
                new C4Element('api', C4ElementKind::System, new Label('API')),
                new C4Element('api', C4ElementKind::SystemExternal, new Label('External API')),
            ],
        );
    }

    public function testDiagramRejectsElementWithUnknownBoundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 element "api" references unknown boundary "ghost"');

        new C4Diagram(
            C4View::Context,
            [],
            [new C4Element('api', C4ElementKind::System, new Label('API'), boundaryId: 'ghost')],
        );
    }

    public function testDiagramRejectsRelationshipWithUnknownTargetElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 relationship references unknown element "ghost"');

        new C4Diagram(
            C4View::Context,
            [],
            [new C4Element('api', C4ElementKind::System, new Label('API'))],
            [new C4Relationship('api', 'ghost', new Label('calls'))],
        );
    }

    public function testValueObjectsRejectEmptyIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 boundary id must be non-empty.');

        new C4Boundary(' ', new Label('Boundary'));
    }

    public function testElementRejectsEmptyBoundaryId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 element boundary id must be non-empty.');

        new C4Element('api', C4ElementKind::System, new Label('API'), boundaryId: ' ');
    }

    public function testElementRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 element id must be non-empty.');

        new C4Element(' ', C4ElementKind::System, new Label('API'));
    }

    public function testRelationshipRejectsEmptyEndpointIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 relationship source id must be non-empty.');

        new C4Relationship(' ', 'api', new Label('calls'));
    }

    public function testRelationshipRejectsEmptyTargetId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('C4 relationship target id must be non-empty.');

        new C4Relationship('user', ' ', new Label('calls'));
    }
}
