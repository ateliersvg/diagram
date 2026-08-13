<?php

declare(strict_types=1);

use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;

function buildClassDiagram(): ClassDiagram
{
    return (new ClassDiagramBuilder())
        ->member('User', '+id int')
        ->member('User', '+email string')
        ->member('Order', '+total Money')
        ->member('Order', '+paid bool')
        ->relation('User', 'Order', 'places')
        ->build();
}

function classDiagramMermaid(): string
{
    return <<<'MERMAID'
        classDiagram
            class User
            User : +id int
            User : +email string
            class Order
            Order : +total Money
            Order : +paid bool
            User --> Order : places
        MERMAID;
}
