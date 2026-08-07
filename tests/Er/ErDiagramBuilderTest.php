<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Er;

use Atelier\Diagram\Er\ErAttribute;
use Atelier\Diagram\Er\ErCardinality;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Er\ErEntity;
use Atelier\Diagram\Er\ErRelationship;
use Atelier\Diagram\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErDiagramBuilder::class)]
#[CoversClass(ErDiagram::class)]
#[CoversClass(ErEntity::class)]
#[CoversClass(ErAttribute::class)]
#[CoversClass(ErRelationship::class)]
#[CoversClass(ErCardinality::class)]
final class ErDiagramBuilderTest extends TestCase
{
    public function testBuildsEntitiesAttributesAndRelationships(): void
    {
        $diagram = (new ErDiagramBuilder())
            ->entity('CUSTOMER')
            ->attribute('CUSTOMER', 'string', 'email')
            ->attribute('ORDER', 'decimal', 'total')
            ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
            ->build();

        $this->assertSame('CUSTOMER', $diagram->entities[0]->id);
        $this->assertSame('email', $diagram->entities[0]->attributes[0]->name);
        $this->assertSame(ErCardinality::ExactlyOne, $diagram->relationships[0]->fromCardinality);
        $this->assertSame(ErCardinality::ZeroOrMore, $diagram->relationships[0]->toCardinality);
        $this->assertSame('places', $diagram->relationships[0]->label?->text);
        $this->assertSame('string email', $diagram->entities[0]->attributes[0]->text());
        $this->assertSame('||--o{', $diagram->relationships[0]->edgeToken());
    }

    public function testRejectsUnsupportedCardinality(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported ER cardinality');

        (new ErDiagramBuilder())->relationship('A', '}o', 'B', '||');
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER diagram must contain at least one entity');

        (new ErDiagramBuilder())->build();
    }

    public function testRejectsEmptyAttributeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER attribute type must be non-empty.');

        new ErAttribute('  ', 'email');
    }

    public function testRejectsEmptyAttributeName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER attribute name must be non-empty.');

        new ErAttribute('string', '  ');
    }

    public function testRejectsEmptyEntityId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER entity id must be non-empty.');

        new ErEntity('  ');
    }

    public function testRejectsDuplicateEntityId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate ER entity id "CUSTOMER".');

        new ErDiagram([new ErEntity('CUSTOMER'), new ErEntity('CUSTOMER')]);
    }

    public function testRejectsRelationshipWithUnknownFromEntity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER relationship references unknown entity "GHOST".');

        new ErDiagram(
            [new ErEntity('CUSTOMER')],
            [new ErRelationship('GHOST', ErCardinality::ExactlyOne, 'CUSTOMER', ErCardinality::ZeroOrMore)],
        );
    }

    public function testRejectsRelationshipWithUnknownToEntity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER relationship references unknown entity "GHOST".');

        new ErDiagram(
            [new ErEntity('CUSTOMER')],
            [new ErRelationship('CUSTOMER', ErCardinality::ExactlyOne, 'GHOST', ErCardinality::ZeroOrMore)],
        );
    }

    public function testRejectsEmptyRelationshipEndpoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER relationship endpoints must be non-empty.');

        new ErRelationship('', ErCardinality::ExactlyOne, 'CUSTOMER', ErCardinality::ZeroOrMore);
    }

    public function testBuilderRejectsEmptyEntityId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ER entity id must be non-empty.');

        (new ErDiagramBuilder())->entity('  ');
    }
}
