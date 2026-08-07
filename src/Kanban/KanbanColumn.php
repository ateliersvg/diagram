<?php

declare(strict_types=1);

namespace Atelier\Diagram\Kanban;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class KanbanColumn
{
    /**
     * @param list<KanbanCard> $cards
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $cards = [],
    ) {
        KanbanCard::assertIdentifier($id, 'Kanban column id');

        if ('' === trim($label)) {
            throw new InvalidArgumentException('Kanban column label must be non-empty.');
        }
    }
}
