<?php

declare(strict_types=1);

use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;

function buildSequenceWorkflow(): SequenceDiagram
{
    return (new SequenceDiagramBuilder())
        ->title('Checkout sequence')
        ->participant('User', 'Customer')
        ->participant('App')
        ->participant('Api', 'API')
        ->message('User', 'App', 'Confirm cart')
        ->message('App', 'Api', 'Create order')
        ->activate('Api')
        ->message('Api', 'App', 'Order accepted', MessageArrow::Dashed)
        ->deactivate('Api')
        ->block(SequenceBlockKind::Alt, 'payment accepted', 1, 3, [
            new SequenceBlockBranch('payment accepted', 1, 2),
            new SequenceBlockBranch('manual review', 3, 3),
        ])
        ->message('App', 'Api', 'Review order')
        ->message('Api', 'App', 'Review required', MessageArrow::Dashed)
        ->message('App', 'User', 'Show status')
        ->message('User', 'App', 'Refresh')
        ->block(SequenceBlockKind::Loop, 'status polling', 5, 6)
        ->message('App', 'Api', 'Fetch status')
        ->build();
}

function sequenceWorkflowMermaid(): string
{
    return <<<'MERMAID'
        sequenceDiagram
            title Checkout sequence
            participant User as Customer
            participant App
            participant Api as API
            User->>App: Confirm cart
            alt payment accepted
            App->>Api: Create order
            activate Api
            Api-->>App: Order accepted
            deactivate Api
            else manual review
            App->>Api: Review order
            end
            Api-->>App: Review required
            loop status polling
            App->>User: Show status
            User->>App: Refresh
            end
            App->>Api: Fetch status
        MERMAID;
}
