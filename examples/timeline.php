<?php

declare(strict_types=1);

use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;

function buildTimelineDiagram(): TimelineDiagram
{
    return (new TimelineDiagramBuilder())
        ->title('Release timeline')
        ->section('Discovery')
        ->event('Research', 'Jan')
        ->event('Prototype', 'Feb')
        ->event('Decision', 'Mar')
        ->section('Build')
        ->event('API freeze', 'Apr')
        ->event('Private beta', 'May')
        ->event('Launch', 'Jun')
        ->section('Operate')
        ->event('Metrics', 'Jul')
        ->event('Iteration', 'Aug')
        ->event('Scale-up', 'Sep')
        ->build();
}

function timelineMermaid(): string
{
    return <<<'MERMAID'
        timeline
            title Release timeline
            section Discovery
                Research : Jan
                Prototype : Feb
                Decision : Mar
            section Build
                API freeze : Apr
                Private beta : May
                Launch : Jun
            section Operate
                Metrics : Jul
                Iteration : Aug
                Scale-up : Sep
        MERMAID;
}
