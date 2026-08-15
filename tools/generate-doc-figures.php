<?php

declare(strict_types=1);

/*
 * Regenerates docs/images/*.svg from the examples documented by each page.
 * Intro previews omit optional titles because the page heading already names
 * the type. Adaptive figures keep a semantic canvas colour for label halos and
 * badge text, then make only the outermost SVG canvas transparent for embedding.
 */

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';

foreach ([
    'state-machine', 'venn-diagrams', 'git-history', 'sequence-workflow',
    'flowchart-workflow', 'class-diagram', 'er-diagram', 'timeline', 'journey',
    'mindmap', 'requirement-diagram', 'kanban', 'block-diagram', 'architecture', 'c4',
] as $example) {
    require dirname(__DIR__).'/examples/'.$example.'.php';
}

$theme = static fn (?string $mark = null): Theme => new Theme(
    backgroundColor: 'var(--figure-canvas, #05070c)',
    nodeFillColor: 'var(--surface-1, #14141c)',
    nodeStrokeColor: $mark ?? 'var(--color-brand, #6ea8fe)',
    textColor: 'var(--ink-0, #e6e6ea)',
    mutedTextColor: 'var(--ink-1, #9a9aa6)',
    accentColors: array_values(array_filter([
        $mark,
        'var(--color-brand, #6ea8fe)',
        'var(--doc-figure-2, #7e73ee)',
        'var(--doc-figure-3, #4bb3a5)',
        'var(--doc-figure-4, #d08a4f)',
        'var(--doc-figure-5, #c46b8a)',
    ])),
    fontFamily: 'system-ui, -apple-system, Helvetica, Arial, sans-serif',
    fontSize: 15.0,
    spacingUnit: 9.0,
    strokeWidth: 1.8,
);

$adaptiveSvg = static function (Diagram $diagram, Theme $figureTheme): string {
    $svg = $diagram->toSvg($figureTheme);
    $transparent = preg_replace(
        '/(<rect x="0" y="0" width="[^"]+" height="[^"]+" fill=")[^"]+("\/>)/',
        '$1transparent$2',
        $svg,
        1,
        $replacementCount,
    );
    if (null === $transparent || 1 !== $replacementCount) {
        throw new RuntimeException('Could not locate the diagram canvas in generated SVG.');
    }

    return $transparent;
};

$previewSvg = static function (Diagram $diagram, Theme $figureTheme) use ($adaptiveSvg): string {
    $source = preg_replace('/^\h*title(?:\h+|:\h*).*(?:\R|$)/m', '', $diagram->toMermaid());
    if (null === $source) {
        throw new RuntimeException('Could not remove the title from the preview source.');
    }

    return $adaptiveSvg(Diagram::fromMermaid($source), $figureTheme);
};

$base = $theme();
$marked = $theme('var(--accent, #7e73ee)');
$figures = [
    'state-diagram' => $previewSvg(Diagram::of(buildStateMachineDiagram(Direction::TopToBottom, 'Order lifecycle')), $base),
    'flowchart' => $previewSvg(Diagram::of(buildFlowchartWorkflow()), $base),
    'git-graph' => $previewSvg(Diagram::of(buildGitHistory('Release history')), $base),
    'sequence-diagram' => $previewSvg(Diagram::of(buildSequenceWorkflow()), $base),
    'class-diagram' => $previewSvg(Diagram::of(buildClassDiagram()), $base),
    'er-diagram' => $previewSvg(Diagram::of(buildErDiagram()), $base),
    'venn-diagram' => $adaptiveSvg(Diagram::of(buildVenn3(null)), $base),
    'timeline-diagram' => $previewSvg(Diagram::of(buildTimelineDiagram()), $base),
    'journey-diagram' => $previewSvg(Diagram::of(buildJourneyDiagram()), $base),
    'mindmap-diagram' => $previewSvg(Diagram::of(buildMindmap()), $base),
    'requirement-diagram' => $previewSvg(Diagram::of(buildRequirementDiagram()), $base),
    'kanban-diagram' => $previewSvg(Diagram::of(buildKanbanDiagram()), $base),
    'block-diagram' => $previewSvg(Diagram::of(buildBlockDiagram()), $base),
    'architecture-diagram' => $previewSvg(Diagram::of(buildArchitectureDiagram()), $base),
    'c4-diagram' => $previewSvg(Diagram::of(buildC4Diagram()), $base),
    'state-diagram-direction' => $adaptiveSvg(Diagram::of(buildStateMachineDiagram(Direction::LeftToRight, 'Order lifecycle')), $marked),
    'venn-diagram-two-sets' => $adaptiveSvg(Diagram::of(buildVenn2()), $marked),
    'git-graph-no-title' => $adaptiveSvg(Diagram::of(buildGitHistory()), $marked),
    'class-diagram-theme' => Diagram::of(buildClassDiagram())->toSvg(Theme::blueprint()),
    'er-diagram-theme' => Diagram::of(buildErDiagram())->toSvg(Theme::blueprint()),
    'sequence-diagram-theme' => Diagram::of(buildSequenceWorkflow())->toSvg(Theme::mono()),
    'timeline-diagram-theme' => Diagram::of(buildTimelineDiagram())->toSvg(Theme::blueprint()),
    'journey-diagram-theme' => Diagram::of(buildJourneyDiagram())->toSvg(Theme::mono()),
    'mindmap-diagram-theme' => Diagram::of(buildMindmap())->toSvg(Theme::blueprint()),
    'requirement-diagram-theme' => Diagram::of(buildRequirementDiagram())->toSvg(Theme::mono()),
    'kanban-diagram-theme' => Diagram::of(buildKanbanDiagram())->toSvg(Theme::blueprint()),
    'block-diagram-theme' => Diagram::of(buildBlockDiagram())->toSvg(Theme::mono()),
    'architecture-diagram-theme' => Diagram::of(buildArchitectureDiagram())->toSvg(Theme::blueprint()),
    'c4-diagram-theme' => Diagram::of(buildC4Diagram())->toSvg(Theme::neutral()),
];

$figures['flowchart-direction'] = $adaptiveSvg(
    Diagram::fromMermaid(str_replace('flowchart TD', 'flowchart LR', flowchartWorkflowMermaid())),
    $marked,
);

foreach ([
    'default' => Theme::default(),
    'dark' => Theme::dark(),
    'blueprint' => Theme::blueprint(),
    'mono' => Theme::mono(),
    'neutral' => Theme::neutral(),
] as $name => $preset) {
    $figures['theme-'.$name] = Diagram::of(buildStateMachineDiagram(Direction::LeftToRight))->toSvg($preset);
}

$output = dirname(__DIR__).'/docs/images';
foreach ($figures as $slug => $svg) {
    $target = $output.'/'.$slug.'.svg';
    if (false === file_put_contents($target, $svg)) {
        throw new RuntimeException(sprintf('Could not write diagram figure: %s', $target));
    }
}

echo 'Wrote '.count($figures)." figures to {$output}\n";
