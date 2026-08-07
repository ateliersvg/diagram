<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Kanban;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Kanban\KanbanCard;
use Atelier\Diagram\Kanban\KanbanColumn;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KanbanDiagramBuilder::class)]
#[CoversClass(KanbanDiagram::class)]
#[CoversClass(KanbanColumn::class)]
#[CoversClass(KanbanCard::class)]
final class KanbanDiagramBuilderTest extends TestCase
{
    public function testBuildsColumnsAndCardsInOrder(): void
    {
        $diagram = (new KanbanDiagramBuilder())
            ->title('Delivery board')
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser')
            ->card('todo', 'UI-2', 'Review SVG output')
            ->column('done', 'Done')
            ->card('done', 'CORE-1', 'Scene renderer')
            ->build();

        $this->assertSame('Delivery board', $diagram->title?->text);
        $this->assertSame('todo', $diagram->columns[0]->id);
        $this->assertSame('REQ-1', $diagram->columns[0]->cards[0]->id);
        $this->assertSame('UI-2', $diagram->columns[0]->cards[1]->id);
        $this->assertSame('done', $diagram->columns[1]->id);
    }

    public function testRejectsDuplicateColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate kanban column id "todo"');

        (new KanbanDiagramBuilder())
            ->column('todo', 'Todo')
            ->column('todo', 'Backlog');
    }

    public function testRejectsCardInUnknownColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown column "todo"');

        (new KanbanDiagramBuilder())->card('todo', 'REQ-1', 'Write parser');
    }

    public function testRejectsInvalidIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban card id must match');

        (new KanbanDiagramBuilder())
            ->column('todo', 'Todo')
            ->card('todo', '1-REQ', 'Write parser');
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban diagram must contain at least one column');

        (new KanbanDiagramBuilder())->build();
    }

    public function testRejectsDuplicateCard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate kanban card id "REQ-1"');

        (new KanbanDiagramBuilder())
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser')
            ->card('todo', 'REQ-1', 'Write parser again');
    }

    public function testRejectsEmptyColumnLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban column label must be non-empty');

        (new KanbanDiagramBuilder())->column('todo', '  ');
    }

    public function testNonEmptyColumnsGuardRejectsEmptyList(): void
    {
        $method = new \ReflectionMethod(KanbanDiagramBuilder::class, 'nonEmptyColumns');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban diagram must contain at least one column');

        $method->invoke(new KanbanDiagramBuilder(), []);
    }

    public function testCardAcceptsValidConstruction(): void
    {
        $card = new KanbanCard('REQ-1', 'Write parser');

        $this->assertSame('REQ-1', $card->id);
        $this->assertSame('Write parser', $card->label);
    }

    public function testCardRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban card label must be non-empty');

        new KanbanCard('REQ-1', '  ');
    }

    public function testColumnAcceptsValidConstruction(): void
    {
        $column = new KanbanColumn('todo', 'Todo', [new KanbanCard('REQ-1', 'Write parser')]);

        $this->assertSame('todo', $column->id);
        $this->assertSame('Todo', $column->label);
        $this->assertCount(1, $column->cards);
    }

    public function testColumnRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban column label must be non-empty');

        new KanbanColumn('todo', '  ');
    }
}
