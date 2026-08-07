<?php

declare(strict_types=1);

namespace Atelier\Diagram\Kanban;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Title;

final class KanbanDiagramBuilder
{
    /**
     * @var list<array{id: string, label: string, cards: list<KanbanCard>}>
     */
    private array $columns = [];

    /**
     * @var array<string, int>
     */
    private array $columnIndexes = [];

    /**
     * @var array<string, true>
     */
    private array $cardIds = [];

    private ?Title $title = null;

    public function title(string $text): self
    {
        $this->title = new Title($text);

        return $this;
    }

    public function column(string $id, string $label): self
    {
        KanbanCard::assertIdentifier($id, 'Kanban column id');

        if ('' === trim($label)) {
            throw new InvalidArgumentException('Kanban column label must be non-empty.');
        }
        if (isset($this->columnIndexes[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate kanban column id "%s".', $id));
        }

        $this->columnIndexes[$id] = \count($this->columns);
        $this->columns[] = ['id' => $id, 'label' => $label, 'cards' => []];

        return $this;
    }

    public function card(string $columnId, string $id, string $label): self
    {
        if (!isset($this->columnIndexes[$columnId])) {
            throw new InvalidArgumentException(\sprintf('Kanban card references unknown column "%s".', $columnId));
        }
        if (isset($this->cardIds[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate kanban card id "%s".', $id));
        }

        $card = new KanbanCard($id, $label);
        $columnIndex = $this->columnIndexes[$columnId];
        $this->columns[$columnIndex]['cards'][] = $card;
        $this->cardIds[$id] = true;

        return $this;
    }

    public function build(): KanbanDiagram
    {
        if ([] === $this->columns) {
            throw new InvalidArgumentException('Kanban diagram must contain at least one column.');
        }

        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = new KanbanColumn($column['id'], $column['label'], $column['cards']);
        }

        return new KanbanDiagram($this->nonEmptyColumns($columns), $this->title);
    }

    /**
     * @param list<KanbanColumn> $columns
     *
     * @return non-empty-list<KanbanColumn>
     */
    private function nonEmptyColumns(array $columns): array
    {
        if ([] === $columns) {
            throw new InvalidArgumentException('Kanban diagram must contain at least one column.');
        }

        return $columns;
    }
}
