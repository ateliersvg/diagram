<?php

declare(strict_types=1);

/*
 * Section-3.2 gallery: regenerates every artifact in examples/output/ and
 * prints each path written. Models go through the Diagram facade; the
 * hand-built smoke Scene has no model, so it goes through SvgRenderer
 * directly.
 *
 * Output must stay byte-identical to what the per-type examples
 * (venn.php, state.php, git.php, markdown.php, render-smoke.php) produce:
 * the layout/parser snapshot tests freeze these exact files.
 */

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/state-machine.php';
require __DIR__.'/venn-diagrams.php';
require __DIR__.'/git-history.php';
require __DIR__.'/sequence-workflow.php';
require __DIR__.'/flowchart-workflow.php';
require __DIR__.'/class-diagram.php';
require __DIR__.'/er-diagram.php';
require __DIR__.'/timeline.php';
require __DIR__.'/journey.php';
require __DIR__.'/mindmap.php';
require __DIR__.'/requirement-diagram.php';
require __DIR__.'/kanban.php';
require __DIR__.'/block-diagram.php';
require __DIR__.'/architecture.php';
require __DIR__.'/c4.php';
require __DIR__.'/smoke-scene.php';

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

// Reference SVGs, through the facade.
$svgs = [
    'venn-2.svg' => Diagram::of(buildVenn2()),
    'venn-3.svg' => Diagram::of(buildVenn3()),
    'state-tb.svg' => Diagram::of(buildStateMachineDiagram(Direction::TopToBottom, 'Order lifecycle')),
    'state-lr.svg' => Diagram::of(buildStateMachineDiagram(Direction::LeftToRight, 'Order lifecycle')),
    'state-parsed.svg' => Diagram::fromMermaid(stateMachineMermaid()),
    'git-builder.svg' => Diagram::of(buildGitHistory('Release history')),
    'git-parsed.svg' => Diagram::fromMermaid(gitHistoryMermaid()),
    'sequence-builder.svg' => Diagram::of(buildSequenceWorkflow()),
    'sequence-parsed.svg' => Diagram::fromMermaid(sequenceWorkflowMermaid()),
    'flowchart-builder.svg' => Diagram::of(buildFlowchartWorkflow()),
    'flowchart-parsed.svg' => Diagram::fromMermaid(flowchartWorkflowMermaid()),
    'class-builder.svg' => Diagram::of(buildClassDiagram()),
    'class-parsed.svg' => Diagram::fromMermaid(classDiagramMermaid()),
    'er-builder.svg' => Diagram::of(buildErDiagram()),
    'er-parsed.svg' => Diagram::fromMermaid(erDiagramMermaid()),
    'timeline-builder.svg' => Diagram::of(buildTimelineDiagram()),
    'timeline-parsed.svg' => Diagram::fromMermaid(timelineMermaid()),
    'journey-builder.svg' => Diagram::of(buildJourneyDiagram()),
    'journey-parsed.svg' => Diagram::fromMermaid(journeyMermaid()),
    'mindmap-builder.svg' => Diagram::of(buildMindmap()),
    'mindmap-parsed.svg' => Diagram::fromMermaid(mindmapMermaid()),
    'requirement-builder.svg' => Diagram::of(buildRequirementDiagram()),
    'requirement-parsed.svg' => Diagram::fromMermaid(requirementDiagramMermaid()),
    'kanban-builder.svg' => Diagram::of(buildKanbanDiagram()),
    'kanban-parsed.svg' => Diagram::fromMermaid(kanbanMermaid()),
    'block-builder.svg' => Diagram::of(buildBlockDiagram()),
    'block-parsed.svg' => Diagram::fromMermaid(blockDiagramMermaid()),
    'architecture-builder.svg' => Diagram::of(buildArchitectureDiagram()),
    'architecture-parsed.svg' => Diagram::fromMermaid(architectureMermaid()),
    'c4-builder.svg' => Diagram::of(buildC4Diagram()),
    'c4-parsed.svg' => Diagram::fromMermaid(c4Mermaid()),
];

foreach ($svgs as $name => $diagram) {
    $target = $outputDir.'/'.$name;
    $diagram->saveSvg($target);
    echo 'Wrote '.$target.\PHP_EOL;
}

// Markdown, for the diagram types with a Mermaid grammar (untitled models:
// the grammar has no title statement).
$markdowns = [
    'state.md' => Diagram::of(buildStateMachineDiagram()),
    'git.md' => Diagram::of(buildGitHistory()),
    'sequence.md' => Diagram::of(buildSequenceWorkflow()),
    'flowchart.md' => Diagram::of(buildFlowchartWorkflow()),
    'class.md' => Diagram::of(buildClassDiagram()),
    'er.md' => Diagram::of(buildErDiagram()),
    'timeline.md' => Diagram::of(buildTimelineDiagram()),
    'journey.md' => Diagram::of(buildJourneyDiagram()),
    'mindmap.md' => Diagram::of(buildMindmap()),
    'requirement.md' => Diagram::of(buildRequirementDiagram()),
    'kanban.md' => Diagram::of(buildKanbanDiagram()),
    'block.md' => Diagram::of(buildBlockDiagram()),
    'architecture.md' => Diagram::of(buildArchitectureDiagram()),
    'c4.md' => Diagram::of(buildC4Diagram()),
];

foreach ($markdowns as $name => $diagram) {
    $target = $outputDir.'/'.$name;
    file_put_contents($target, $diagram->toMarkdown());
    echo 'Wrote '.$target.\PHP_EOL;
}

// The renderer smoke Scene, below the facade (positioned primitives, no model).
$target = $outputDir.'/scene-smoke.svg';
file_put_contents($target, (new SvgRenderer())->render(buildSmokeScene()));
echo 'Wrote '.$target.\PHP_EOL;
