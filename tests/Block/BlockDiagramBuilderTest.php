<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Block;

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\Block\BlockGroup;
use Atelier\Diagram\Block\BlockNode;
use Atelier\Diagram\Block\BlockRelationship;
use Atelier\Diagram\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockDiagramBuilder::class)]
#[CoversClass(BlockDiagram::class)]
#[CoversClass(BlockNode::class)]
#[CoversClass(BlockGroup::class)]
#[CoversClass(BlockRelationship::class)]
final class BlockDiagramBuilderTest extends TestCase
{
    public function testBuildsBlocksGroupsAndRelationships(): void
    {
        $diagram = (new BlockDiagramBuilder())
            ->title('Layout kernel')
            ->block('Solver', 'LayoutSolver')
            ->beginGroup('Primitives', 'Primitives')
                ->block('Rect', 'Rect')
                ->block('Insets', 'Insets')
            ->endGroup()
            ->relationship('Solver', 'Rect', 'places')
            ->build();

        $this->assertSame('Layout kernel', $diagram->title?->text);
        $this->assertCount(3, $diagram->nodes);
        $this->assertSame('Primitives', $diagram->groups[0]->label);
        $this->assertSame(['Rect', 'Insets'], $diagram->groups[0]->nodeIds);
        $this->assertSame('places', $diagram->relationships[0]->label?->text);
    }

    public function testNestedGroupsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested block groups are not supported yet');

        (new BlockDiagramBuilder())
            ->beginGroup('A')
            ->beginGroup('B');
    }

    public function testGroupMustContainBlock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain at least one block');

        (new BlockDiagramBuilder())
            ->beginGroup('Empty')
            ->endGroup()
            ->block('A')
            ->build();
    }

    public function testRelationshipAutoCreatesMissingEndpoints(): void
    {
        $diagram = (new BlockDiagramBuilder())
            ->relationship('A', 'B')
            ->build();

        $this->assertCount(2, $diagram->nodes);
        $this->assertSame('A', $diagram->nodes[0]->id);
        $this->assertSame('B', $diagram->nodes[1]->id);
    }

    public function testEndGroupBeforeBeginIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot end a block group before starting one');

        (new BlockDiagramBuilder())->endGroup();
    }

    public function testBuildWithOpenGroupIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not closed');

        (new BlockDiagramBuilder())
            ->beginGroup('Open')
            ->block('a')
            ->build();
    }

    public function testBuildWithoutBlocksIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one block');

        (new BlockDiagramBuilder())->build();
    }

    public function testDuplicateGroupIdInBuilderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate block group id "A"');

        (new BlockDiagramBuilder())
            ->beginGroup('A')
            ->block('a')
            ->endGroup()
            ->beginGroup('A');
    }

    public function testNonEmptyNodesRejectsEmptyState(): void
    {
        $builder = new BlockDiagramBuilder();
        $method = new \ReflectionMethod($builder, 'nonEmptyNodes');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one block');

        $method->invoke($builder);
    }

    public function testRejectsEmptyNodeList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one block');

        new BlockDiagram([]);
    }

    public function testRejectsDuplicateNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate block id "a"');

        new BlockDiagram([new BlockNode('a', 'a'), new BlockNode('a', 'a')]);
    }

    public function testRejectsDuplicateGroupId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate block group id "g"');

        new BlockDiagram(
            [new BlockNode('a', 'a')],
            [new BlockGroup('g', 'g', ['a']), new BlockGroup('g', 'g', ['a'])],
        );
    }

    public function testRejectsGroupReferencingUnknownNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown block "ghost"');

        new BlockDiagram(
            [new BlockNode('a', 'a')],
            [new BlockGroup('g', 'g', ['ghost'])],
        );
    }

    public function testRejectsNodeReferencingUnknownGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown group "ghost"');

        new BlockDiagram([new BlockNode('a', 'a', 'ghost')]);
    }

    public function testRejectsRelationshipWithUnknownFrom(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('relationship references unknown block "ghost"');

        new BlockDiagram(
            [new BlockNode('a', 'a')],
            [],
            [new BlockRelationship('ghost', 'a')],
        );
    }

    public function testRejectsRelationshipWithUnknownTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('relationship references unknown block "ghost"');

        new BlockDiagram(
            [new BlockNode('a', 'a')],
            [],
            [new BlockRelationship('a', 'ghost')],
        );
    }

    public function testRejectsEmptyNodeIdInConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block id must be non-empty');

        new BlockNode('  ', 'label');
    }

    public function testRejectsEmptyNodeLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block label must be non-empty');

        new BlockNode('a', '  ');
    }

    public function testRejectsEmptyGroupId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block group id must be non-empty');

        new BlockGroup('  ', 'label', ['a']);
    }

    public function testRejectsEmptyGroupLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block group label must be non-empty');

        new BlockGroup('g', '  ', ['a']);
    }

    public function testRejectsEmptyGroupNodeIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block group must contain at least one block');

        new BlockGroup('g', 'label', []);
    }

    public function testRejectsEmptyRelationshipEndpoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('endpoints must be non-empty');

        new BlockRelationship('  ', 'a');
    }
}
