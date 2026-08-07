<?php

declare(strict_types=1);

namespace Atelier\Diagram\Kanban;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class KanbanCard
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
        self::assertIdentifier($id, 'Kanban card id');

        if ('' === trim($label)) {
            throw new InvalidArgumentException('Kanban card label must be non-empty.');
        }
    }

    public static function assertIdentifier(string $id, string $what): void
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException(\sprintf('%s must match [A-Za-z_][A-Za-z0-9_-]*, got "%s".', $what, $id));
        }
    }
}
