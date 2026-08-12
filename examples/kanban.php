<?php

declare(strict_types=1);

use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;

function buildKanbanDiagram(): KanbanDiagram
{
    return (new KanbanDiagramBuilder())
        ->title('Delivery board')
        ->column('todo', 'Todo')
        ->card('todo', 'REQ-1', 'Write parser')
        ->card('todo', 'UI-2', 'Review SVG output')
        ->card('todo', 'DOC-4', 'Theme examples')
        ->column('doing', 'Doing')
        ->card('doing', 'LAY-3', 'Layout docs')
        ->card('doing', 'SVG-5', 'Polish renderer')
        ->column('done', 'Done')
        ->card('done', 'SCN-1', 'Scene renderer')
        ->card('done', 'TEST-2', 'Parser corpus')
        ->build();
}

function kanbanMermaid(): string
{
    return <<<'MERMAID'
        kanban
            title Delivery board
            todo [Todo]
                REQ-1 [Write parser]
                UI-2 [Review SVG output]
                DOC-4 [Theme examples]
            doing [Doing]
                LAY-3 [Layout docs]
                SVG-5 [Polish renderer]
            done [Done]
                SCN-1 [Scene renderer]
                TEST-2 [Parser corpus]
        MERMAID;
}
