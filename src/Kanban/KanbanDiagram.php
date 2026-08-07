<?php

declare(strict_types=1);

namespace Atelier\Diagram\Kanban;

use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

final readonly class KanbanDiagram implements DiagramModel
{
    /**
     * @param non-empty-list<KanbanColumn> $columns
     */
    public function __construct(
        public array $columns,
        public ?Title $title = null,
    ) {
    }
}
