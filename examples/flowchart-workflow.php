<?php

declare(strict_types=1);

use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Model\Direction;

function buildFlowchartWorkflow(): Flowchart
{
    return (new FlowchartBuilder())
        ->direction(Direction::TopToBottom)
        ->title('Checkout flow')
        ->node('Cart', 'Cart')
        ->node('Payment', 'Payment')
        ->node('Review', 'Manual review')
        ->node('Fulfill', 'Fulfillment')
        ->edge('Cart', 'Payment', 'pay')
        ->edge('Payment', 'Review', 'needs review')
        ->edge('Payment', 'Fulfill', 'accepted')
        ->edge('Review', 'Fulfill', 'approved')
        ->subgraph('payment', 'Payment checks', ['Payment', 'Review'], 'checkout', 1)
        ->subgraph('checkout', 'Checkout', ['Cart', 'Payment', 'Review', 'Fulfill'])
        ->build();
}

function flowchartWorkflowMermaid(): string
{
    return <<<'MERMAID'
        flowchart TD
            title Checkout flow
            subgraph checkout [Checkout]
                Cart[Cart]
                subgraph payment [Payment checks]
                    Payment[Payment]
                    Review[Manual review]
                end
                Fulfill[Fulfillment]
            end
            Cart -->|pay| Payment
            Payment -->|needs review| Review
            Payment -->|accepted| Fulfill
            Review -->|approved| Fulfill
        MERMAID;
}
