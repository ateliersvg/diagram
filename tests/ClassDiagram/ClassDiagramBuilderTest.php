<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\ClassDiagram;

use Atelier\Diagram\ClassDiagram\ClassBox;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
use Atelier\Diagram\ClassDiagram\ClassMember;
use Atelier\Diagram\ClassDiagram\ClassRelation;
use Atelier\Diagram\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassDiagramBuilder::class)]
#[CoversClass(ClassDiagram::class)]
#[CoversClass(ClassBox::class)]
#[CoversClass(ClassMember::class)]
#[CoversClass(ClassRelation::class)]
final class ClassDiagramBuilderTest extends TestCase
{
    public function testBuildsClassesMembersAndRelations(): void
    {
        $diagram = (new ClassDiagramBuilder())
            ->class('User')
            ->member('User', '+id int')
            ->relation('User', 'Order', 'places')
            ->build();

        $this->assertCount(2, $diagram->classes);
        $this->assertSame('User', $diagram->classes[0]->id);
        $this->assertSame('+id int', $diagram->classes[0]->members[0]->text);
        $this->assertSame('Order', $diagram->classes[1]->id);
        $this->assertSame('places', $diagram->relations[0]->label?->text);
    }

    public function testRejectsEmptyClassBoxId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class id must be non-empty.');

        new ClassBox('  ');
    }

    public function testRejectsEmptyClassDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class diagram must contain at least one class.');

        new ClassDiagram([]);
    }

    public function testRejectsDuplicateClassId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate class id "User".');

        new ClassDiagram([new ClassBox('User'), new ClassBox('User')]);
    }

    public function testRejectsRelationWithUnknownFromClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class relation references unknown class "Ghost".');

        new ClassDiagram([new ClassBox('User')], [new ClassRelation('Ghost', 'User')]);
    }

    public function testRejectsRelationWithUnknownToClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class relation references unknown class "Ghost".');

        new ClassDiagram([new ClassBox('User')], [new ClassRelation('User', 'Ghost')]);
    }

    public function testRejectsEmptyMemberText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class member text must be non-empty.');

        new ClassMember('  ');
    }

    public function testRejectsEmptyRelationEndpoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class relation endpoints must be non-empty.');

        new ClassRelation('', 'User');
    }

    public function testBuilderRejectsEmptyClassId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class id must be non-empty.');

        (new ClassDiagramBuilder())->class('  ');
    }
}
