<?php

declare(strict_types=1);

use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;

function buildMindmap(): MindmapDiagram
{
    return (new MindmapDiagramBuilder())
        ->root('Atelier', 'root')
        ->child('root', 'Layout', 'layout')
        ->child('layout', 'Grid')
        ->child('layout', 'Stack')
        ->child('layout', 'Routing')
        ->child('root', 'Diagram', 'diagram')
        ->child('diagram', 'Sequence')
        ->child('diagram', 'Timeline')
        ->child('diagram', 'ER')
        ->child('root', 'SVG', 'svg')
        ->child('svg', 'Scene')
        ->child('svg', 'Renderer')
        ->build();
}

function mindmapMermaid(): string
{
    return <<<'MERMAID'
        mindmap
          root((Atelier))
            Layout
              Grid
              Stack
              Routing
            Diagram
              Sequence
              Timeline
              ER
            SVG
              Scene
              Renderer
        MERMAID;
}
