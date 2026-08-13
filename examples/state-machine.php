<?php

declare(strict_types=1);

use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\StateDiagramBuilder;

/**
 * Builds the reference order-lifecycle state machine: 6 states, labeled
 * transitions, a self-loop (Review), a back edge (Rejected -> Draft) and
 * a rank holding two states (Approved, Rejected).
 *
 * Shared between examples/state.php and tests. The builder calls mirror
 * stateMachineMermaid() line by line, so both entry points produce the exact
 * same model.
 */
function buildStateMachineDiagram(Direction $direction = Direction::TopToBottom, ?string $title = null): StateDiagram
{
    $builder = (new StateDiagramBuilder())
        ->direction($direction)
        ->state('Review', 'In review')
        ->initial('Draft')
        ->transition('Draft', 'Review', 'submit')
        ->transition('Review', 'Review', 'amend')
        ->transition('Review', 'Approved', 'approve')
        ->transition('Review', 'Rejected', 'reject')
        ->transition('Rejected', 'Draft', 'revise')
        ->transition('Approved', 'Shipped', 'ship')
        ->transition('Shipped', 'Delivered', 'deliver')
        ->state('Delivered', 'Done')
        ->final('Delivered');

    if (null !== $title) {
        $builder->title($title);
    }

    return $builder->build();
}

/**
 * The Mermaid equivalent of buildStateMachineDiagram() (TopToBottom, no title).
 */
function stateMachineMermaid(): string
{
    return <<<'MERMAID'
        stateDiagram-v2
            %% Order lifecycle
            direction TB
            state "In review" as Review
            [*] --> Draft
            Draft --> Review : submit
            Review --> Review : amend
            Review --> Approved : approve
            Review --> Rejected : reject
            Rejected --> Draft : revise
            Approved --> Shipped : ship
            Shipped --> Delivered : deliver
            Delivered : Done
            Delivered --> [*]
        MERMAID;
}
