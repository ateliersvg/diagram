<?php

declare(strict_types=1);

return [
    'state' => Atelier\Diagram\State\StateDiagram::class,
    'state-bom' => Atelier\Diagram\State\StateDiagram::class,
    'state-comments' => Atelier\Diagram\State\StateDiagram::class,
    'git' => Atelier\Diagram\Git\GitGraph::class,
    'sequence' => Atelier\Diagram\Sequence\SequenceDiagram::class,
    'sequence-alt-block' => Atelier\Diagram\Sequence\SequenceDiagram::class,
    'sequence-comments-whitespace' => Atelier\Diagram\Sequence\SequenceDiagram::class,
    'flowchart' => Atelier\Diagram\Flow\Flowchart::class,
    'flowchart-comments-whitespace' => Atelier\Diagram\Flow\Flowchart::class,
    'flowchart-nested-subgraphs' => Atelier\Diagram\Flow\Flowchart::class,
    'class' => Atelier\Diagram\ClassDiagram\ClassDiagram::class,
    'er' => Atelier\Diagram\Er\ErDiagram::class,
    'timeline' => Atelier\Diagram\Timeline\TimelineDiagram::class,
    'journey' => Atelier\Diagram\Journey\JourneyDiagram::class,
    'mindmap' => Atelier\Diagram\Mindmap\MindmapDiagram::class,
    'requirement' => Atelier\Diagram\Requirement\RequirementDiagram::class,
    'kanban' => Atelier\Diagram\Kanban\KanbanDiagram::class,
    'kanban-comments-whitespace' => Atelier\Diagram\Kanban\KanbanDiagram::class,
    'block' => Atelier\Diagram\Block\BlockDiagram::class,
    'architecture' => Atelier\Diagram\Architecture\ArchitectureDiagram::class,
    'c4' => Atelier\Diagram\C4\C4Diagram::class,
];
