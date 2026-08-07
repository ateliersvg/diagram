<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Architecture;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Architecture\ArchitectureDiagramBuilder;
use Atelier\Diagram\Architecture\ArchitectureGroup;
use Atelier\Diagram\Architecture\ArchitectureNode;
use Atelier\Diagram\Architecture\ArchitectureNodeKind;
use Atelier\Diagram\Architecture\ArchitectureRelationship;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchitectureDiagramBuilder::class)]
#[CoversClass(ArchitectureDiagram::class)]
#[CoversClass(ArchitectureGroup::class)]
#[CoversClass(ArchitectureNode::class)]
#[CoversClass(ArchitectureRelationship::class)]
#[CoversClass(ArchitectureNodeKind::class)]
final class ArchitectureDiagramBuilderTest extends TestCase
{
    public function testBuildsGroupsNodesAndRelationshipsInOrder(): void
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

        $this->assertSame('Checkout platform', $diagram->title?->text);
        $this->assertSame('Web', $diagram->groups[0]->id);
        $this->assertSame('Data tier', $diagram->groups[1]->label->text);
        $this->assertSame('App', $diagram->nodes[0]->id);
        $this->assertSame(ArchitectureNodeKind::Database, $diagram->nodes[2]->kind);
        $this->assertSame('writes', $diagram->relationships[1]->label?->text);
    }

    public function testBuildsEveryNodeKindConvenienceMethod(): void
    {
        $diagram = (new ArchitectureDiagramBuilder())
            ->person('User')
            ->system('Sys')
            ->container('Cont')
            ->component('Comp')
            ->database('Db')
            ->queue('Queue')
            ->external('Ext')
            ->build();

        $kinds = array_map(static fn (ArchitectureNode $node): ArchitectureNodeKind => $node->kind, $diagram->nodes);

        $this->assertSame(
            [
                ArchitectureNodeKind::Person,
                ArchitectureNodeKind::System,
                ArchitectureNodeKind::Container,
                ArchitectureNodeKind::Component,
                ArchitectureNodeKind::Database,
                ArchitectureNodeKind::Queue,
                ArchitectureNodeKind::External,
            ],
            $kinds,
        );
    }

    public function testRejectsDuplicateGroups(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate architecture group id "Web"');

        (new ArchitectureDiagramBuilder())
            ->group('Web')
            ->group('Web');
    }

    public function testRejectsDuplicateNodes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate architecture node id "App"');

        (new ArchitectureDiagramBuilder())
            ->component('App')
            ->component('App');
    }

    public function testRejectsUnknownRelationshipEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown node "Api"');

        (new ArchitectureDiagramBuilder())
            ->component('App')
            ->relationship('App', 'Api');
    }

    public function testRejectsUnknownGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown group "Web"');

        (new ArchitectureDiagramBuilder())->component('App', 'Frontend app', 'Web');
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture diagram must contain at least one node');

        (new ArchitectureDiagramBuilder())->build();
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture group id "1bad" is not a supported identifier');

        (new ArchitectureDiagramBuilder())->group('1bad');
    }

    public function testRejectsEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture title must be non-empty');

        (new ArchitectureDiagramBuilder())->title('   ');
    }

    public function testDiagramRejectsDuplicateGroupIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate architecture group id "Web"');

        new ArchitectureDiagram(
            [
                new ArchitectureGroup('Web', new Label('Web')),
                new ArchitectureGroup('Web', new Label('Web again')),
            ],
            [new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('App'))],
        );
    }

    public function testDiagramRejectsDuplicateNodeIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate architecture node id "App"');

        new ArchitectureDiagram(
            [],
            [
                new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('App')),
                new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('App again')),
            ],
        );
    }

    public function testDiagramRejectsNodeWithUnknownGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture node "App" references unknown group "Web"');

        new ArchitectureDiagram(
            [],
            [new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('App'), 'Web')],
        );
    }

    public function testDiagramRejectsRelationshipWithUnknownFrom(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture relationship references unknown node "Ghost"');

        new ArchitectureDiagram(
            [],
            [new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('App'))],
            [new ArchitectureRelationship('Ghost', 'App')],
        );
    }

    public function testDiagramRejectsRelationshipWithUnknownTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture relationship references unknown node "Ghost"');

        new ArchitectureDiagram(
            [],
            [new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('App'))],
            [new ArchitectureRelationship('App', 'Ghost')],
        );
    }

    public function testGroupRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture group id must be non-empty');

        new ArchitectureGroup('   ', new Label('Web'));
    }

    public function testNodeRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture node id must be non-empty');

        new ArchitectureNode('   ', ArchitectureNodeKind::Component, new Label('App'));
    }

    public function testRelationshipRejectsEmptyEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture relationship endpoints must be non-empty');

        new ArchitectureRelationship('App', '   ');
    }
}
