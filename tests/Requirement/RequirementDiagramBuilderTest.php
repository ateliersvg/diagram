<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementDiagramBuilder;
use Atelier\Diagram\Requirement\RequirementNode;
use Atelier\Diagram\Requirement\RequirementNodeKind;
use Atelier\Diagram\Requirement\RequirementRelationship;
use Atelier\Diagram\Requirement\RequirementRelationshipKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequirementDiagramBuilder::class)]
#[CoversClass(RequirementDiagram::class)]
#[CoversClass(RequirementNode::class)]
#[CoversClass(RequirementNodeKind::class)]
#[CoversClass(RequirementRelationship::class)]
#[CoversClass(RequirementRelationshipKind::class)]
final class RequirementDiagramBuilderTest extends TestCase
{
    public function testBuildsNodesFieldsAndRelationships(): void
    {
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout', ['id' => 'REQ-1', 'risk' => 'medium'])
            ->element('cart', ['type' => 'component'])
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();

        $this->assertSame('checkout', $diagram->nodes[0]->id);
        $this->assertSame(RequirementNodeKind::Requirement, $diagram->nodes[0]->kind);
        $this->assertSame('REQ-1', $diagram->nodes[0]->fields['id']);
        $this->assertSame(RequirementNodeKind::Element, $diagram->nodes[1]->kind);
        $this->assertSame(RequirementRelationshipKind::Satisfies, $diagram->relationships[0]->kind);
    }

    public function testRejectsUnknownRelationshipKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported requirement relationship kind');

        (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->element('cart')
            ->relationship('cart', 'breaks', 'checkout');
    }

    public function testRejectsRelationshipToUnknownNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown node "checkout"');

        (new RequirementDiagramBuilder())
            ->element('cart')
            ->relationship('cart', 'satisfies', 'checkout')
            ->build();
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement diagram must contain at least one node');

        (new RequirementDiagramBuilder())->build();
    }

    public function testNodesOfKindFiltersByKind(): void
    {
        $diagram = (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->element('cart')
            ->build();

        $elements = $diagram->nodesOfKind(RequirementNodeKind::Element);

        $this->assertCount(1, $elements);
        $this->assertSame('cart', $elements[0]->id);
    }

    public function testRejectsConflictingNodeKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be both');

        (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->element('checkout');
    }

    public function testRejectsRelationshipFromUnknownNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown node "ghost"');

        (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->relationship('ghost', 'satisfies', 'checkout')
            ->build();
    }

    public function testRejectsDuplicateNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate requirement diagram node id "checkout"');

        new RequirementDiagram([
            new RequirementNode('checkout', RequirementNodeKind::Requirement),
            new RequirementNode('checkout', RequirementNodeKind::Element),
        ]);
    }

    public function testRejectsFieldOnUnknownNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add requirement field to unknown node "ghost"');

        (new RequirementDiagramBuilder())->field('ghost', 'id', 'REQ-1');
    }

    public function testRejectsEmptyFieldValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement diagram field "id" must be non-empty');

        (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->field('checkout', 'id', '   ');
    }

    public function testRejectsInvalidNodeIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement diagram node id "1bad" must be a Mermaid-style identifier');

        (new RequirementDiagramBuilder())->requirement('1bad');
    }

    public function testRejectsInvalidFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement diagram field name "1bad" must be a simple identifier');

        (new RequirementDiagramBuilder())
            ->requirement('checkout')
            ->field('checkout', '1bad', 'value');
    }

    public function testRejectsEmptyNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement diagram node id must be non-empty');

        new RequirementNode('   ', RequirementNodeKind::Requirement);
    }

    public function testRejectsEmptyNodeFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains an empty field name');

        new RequirementNode('checkout', RequirementNodeKind::Requirement, ['   ' => 'value']);
    }

    public function testRejectsEmptyNodeFieldValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field "id" must be non-empty');

        new RequirementNode('checkout', RequirementNodeKind::Requirement, ['id' => '   ']);
    }

    public function testRejectsEmptyRelationshipEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement relationship endpoints must be non-empty');

        new RequirementRelationship('', 'checkout', RequirementRelationshipKind::Satisfies);
    }
}
