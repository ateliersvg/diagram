<?php

declare(strict_types=1);

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/architecture.php';
require __DIR__.'/block-diagram.php';
require __DIR__.'/class-diagram.php';
require __DIR__.'/er-diagram.php';
require __DIR__.'/flowchart-workflow.php';
require __DIR__.'/git-history.php';
require __DIR__.'/journey.php';
require __DIR__.'/kanban.php';
require __DIR__.'/mindmap.php';
require __DIR__.'/requirement-diagram.php';
require __DIR__.'/state-machine.php';
require __DIR__.'/timeline.php';
require __DIR__.'/venn-diagrams.php';

$outputDir = __DIR__.'/output/effects-demo';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

$variants = [
    [
        'slug' => 'mono-transparent',
        'name' => 'Black strokes',
        'caption' => 'Transparent SVG, black strokes, system-ui labels.',
        'media' => effectsDemoVariantMedia(effectsDemoThemeMono(), 'mono'),
    ],
    [
        'slug' => 'white-black',
        'name' => 'White on black',
        'caption' => 'White strokes on black with a professional condensed sans.',
        'media' => effectsDemoVariantMedia(effectsDemoThemeWhiteBlack(), 'white-black'),
    ],
    [
        'slug' => 'neutral-fill',
        'name' => 'Neutral fills',
        'caption' => 'Two neutral fill levels, nearly no stroke, humanist Optima-style labels.',
        'media' => effectsDemoVariantMedia(effectsDemoThemeNeutral(), 'neutral'),
    ],
    [
        'slug' => 'post-its',
        'name' => 'Post-its',
        'caption' => 'Oversized sticky notes with marker strokes and casual handwritten labels.',
        'media' => effectsDemoVariantMedia(effectsDemoThemePostits(), 'postits'),
    ],
    [
        'slug' => 'blueprint',
        'name' => 'Blueprint',
        'caption' => 'Blue field, grid only behind, mono text, mixed thin and heavy strokes.',
        'media' => effectsDemoVariantMedia(effectsDemoThemeBlueprint(), 'blueprint'),
    ],
    [
        'slug' => 'warm-sketch',
        'name' => 'Warm sketch',
        'caption' => 'Warm white paper, soft off-black strokes, subtle drawn displacement.',
        'media' => effectsDemoVariantMedia(effectsDemoThemeSketch(), 'sketch'),
    ],
    [
        'slug' => 'metro-light',
        'name' => 'Metro light',
        'caption' => 'Rounded colored strokes with a light bevel and a round typeface.',
        'media' => effectsDemoVariantMedia(effectsDemoThemeMetro(), 'metro'),
    ],
];

$target = $outputDir.'/index.html';
file_put_contents($target, effectsDemoHtml($variants));

echo 'Wrote '.$target.\PHP_EOL;

function effectsDemoThemeMono(): Theme
{
    return new Theme(
        backgroundColor: 'transparent',
        nodeFillColor: 'transparent',
        nodeStrokeColor: '#111111',
        textColor: '#111111',
        mutedTextColor: '#4b5563',
        accentColors: ['#111111', '#374151', '#6b7280'],
        fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        fontSize: 14.0,
        spacingUnit: 8.0,
        strokeWidth: 1.6,
    );
}

function effectsDemoThemeWhiteBlack(): Theme
{
    return new Theme(
        backgroundColor: '#030303',
        nodeFillColor: '#030303',
        nodeStrokeColor: '#f8fafc',
        textColor: '#ffffff',
        mutedTextColor: '#d1d5db',
        accentColors: ['#ffffff', '#e5e7eb', '#cbd5e1'],
        fontFamily: '"DIN Condensed", "Avenir Next Condensed", "Helvetica Neue Condensed Bold", "Arial Narrow", sans-serif',
        fontSize: 14.0,
        spacingUnit: 8.0,
        strokeWidth: 1.7,
    );
}

function effectsDemoThemeNeutral(): Theme
{
    return new Theme(
        backgroundColor: '#f5f3ef',
        nodeFillColor: '#e4ded4',
        nodeStrokeColor: '#d8d2c8',
        textColor: '#201f1c',
        mutedTextColor: '#706a60',
        accentColors: ['#e4ded4', '#d7d1c6', '#f5f3ef'],
        fontFamily: 'Optima, Candara, "Gill Sans", sans-serif',
        fontSize: 14.0,
        spacingUnit: 8.0,
        strokeWidth: 0.6,
    );
}

function effectsDemoThemeBlueprint(): Theme
{
    return new Theme(
        backgroundColor: '#06182b',
        nodeFillColor: '#08243d',
        nodeStrokeColor: '#e6fbff',
        textColor: '#ffffff',
        mutedTextColor: '#bae6fd',
        accentColors: ['#67e8f9', '#38bdf8', '#e6fbff'],
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
        fontSize: 13.0,
        spacingUnit: 8.0,
        strokeWidth: 0.95,
    );
}

function effectsDemoThemePostits(): Theme
{
    return new Theme(
        backgroundColor: '#f6ecd8',
        nodeFillColor: '#fff176',
        nodeStrokeColor: '#2f2720',
        textColor: '#201813',
        mutedTextColor: '#5c4635',
        accentColors: ['#fff176', '#ffcc80', '#a7f3d0', '#bfdbfe', '#fbcfe8'],
        fontFamily: '"Marker Felt", "Comic Sans MS", "Chalkboard SE", "Bradley Hand", sans-serif',
        fontSize: 14.0,
        spacingUnit: 8.0,
        strokeWidth: 3.2,
        minNodeWidth: 100.0,
        minNodeHeight: 88.0,
    );
}

function effectsDemoThemeSketch(): Theme
{
    return new Theme(
        backgroundColor: '#fbf6ec',
        nodeFillColor: '#fffdf7',
        nodeStrokeColor: '#2d2821',
        textColor: '#211d18',
        mutedTextColor: '#746a5d',
        accentColors: ['#2d2821', '#7a4e3d', '#5c6f58'],
        fontFamily: '"Bradley Hand", "Comic Sans MS", "Chalkboard SE", Helvetica, Arial, sans-serif',
        fontSize: 14.0,
        spacingUnit: 8.0,
        strokeWidth: 1.7,
    );
}

function effectsDemoThemeMetro(): Theme
{
    return new Theme(
        backgroundColor: '#07111f',
        nodeFillColor: '#0f1b2a',
        nodeStrokeColor: '#67e8f9',
        textColor: '#f8fafc',
        mutedTextColor: '#bfdbfe',
        accentColors: ['#38bdf8', '#f472b6', '#facc15', '#34d399'],
        fontFamily: '"Arial Rounded MT Bold", "Cooper Black", "Trebuchet MS", sans-serif',
        fontSize: 14.0,
        spacingUnit: 8.0,
        strokeWidth: 2.35,
    );
}

function effectsDemoVariantMedia(Theme $theme, string $variant): string
{
    return sprintf(
        <<<'HTML'
        <div class="diagram-pair">
          <figure class="diagram-panel diagram-panel--state">
            <div class="diagram-surface">%s</div>
            <figcaption>State machine</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--flowchart">
            <div class="diagram-surface">%s</div>
            <figcaption>Flowchart</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--sequence">
            <div class="diagram-surface">%s</div>
            <figcaption>Sequence</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--git">
            <div class="diagram-surface">%s</div>
            <figcaption>Git graph</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--venn">
            <div class="diagram-surface">%s</div>
            <figcaption>Venn</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--timeline">
            <div class="diagram-surface">%s</div>
            <figcaption>Timeline</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--block">
            <div class="diagram-surface">%s</div>
            <figcaption>Block</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--mindmap">
            <div class="diagram-surface">%s</div>
            <figcaption>Mindmap</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--class">
            <div class="diagram-surface">%s</div>
            <figcaption>Class</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--er">
            <div class="diagram-surface">%s</div>
            <figcaption>ER</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--journey">
            <div class="diagram-surface">%s</div>
            <figcaption>Journey</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--requirement">
            <div class="diagram-surface">%s</div>
            <figcaption>Requirement</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--kanban">
            <div class="diagram-surface">%s</div>
            <figcaption>Kanban</figcaption>
          </figure>
          <figure class="diagram-panel diagram-panel--architecture">
            <div class="diagram-surface">%s</div>
            <figcaption>Architecture</figcaption>
          </figure>
        </div>
        HTML,
        effectsDemoVariantSvg($theme, $variant, buildStateMachineDiagram(Direction::LeftToRight, 'Order lifecycle'), 'state'),
        effectsDemoVariantSvg($theme, $variant, buildFlowchartWorkflow(), 'flowchart'),
        effectsDemoVariantSvg($theme, $variant, effectsDemoSequenceDiagram(), 'sequence'),
        effectsDemoVariantSvg($theme, $variant, buildGitHistory('Release history'), 'git'),
        effectsDemoVariantSvg($theme, $variant, buildVenn2(), 'venn'),
        effectsDemoVariantSvg($theme, $variant, buildTimelineDiagram(), 'timeline'),
        effectsDemoVariantSvg($theme, $variant, buildBlockDiagram(), 'block'),
        effectsDemoVariantSvg($theme, $variant, buildMindmap(), 'mindmap'),
        effectsDemoVariantSvg($theme, $variant, buildClassDiagram(), 'class'),
        effectsDemoVariantSvg($theme, $variant, buildErDiagram(), 'er'),
        effectsDemoVariantSvg($theme, $variant, buildJourneyDiagram(), 'journey'),
        effectsDemoVariantSvg($theme, $variant, buildRequirementDiagram(), 'requirement'),
        effectsDemoVariantSvg($theme, $variant, buildKanbanDiagram(), 'kanban'),
        effectsDemoVariantSvg($theme, $variant, buildArchitectureDiagram(), 'architecture'),
    );
}

function effectsDemoSequenceDiagram(): SequenceDiagram
{
    return (new SequenceDiagramBuilder())
        ->title('Order handoff')
        ->participant('Buyer', 'Buyer')
        ->participant('App')
        ->participant('Api', 'API')
        ->message('Buyer', 'App', 'Review cart')
        ->message('App', 'Api', 'Create order')
        ->message('Api', 'App', 'Accepted', MessageArrow::Dashed)
        ->message('App', 'Buyer', 'Receipt', MessageArrow::Dashed)
        ->build();
}

function effectsDemoVariantSvg(Theme $theme, string $variant, DiagramModel $model, string $diagram): string
{
    $svg = Diagram::of($model)->toSvg($theme);
    $dom = effectsDemoDom($svg);
    $root = $dom->documentElement;
    if (!$root instanceof DOMElement) {
        throw new RuntimeException('Rendered SVG has no root element.');
    }

    $scope = 'adi-theme-'.$variant.'-'.$diagram;
    effectsDemoAddClass($root, 'adi-svg');
    effectsDemoAddClass($root, $scope);
    effectsDemoAddClass($root, 'adi-diagram-'.$diagram);

    $defs = effectsDemoElement($dom, 'defs');
    $root->insertBefore($defs, $root->firstChild);
    effectsDemoClassifySvg($root);

    match ($variant) {
        'mono' => effectsDemoMono($dom, $defs, $scope),
        'white-black' => effectsDemoWhiteBlack($dom, $root, $defs, $scope),
        'neutral' => effectsDemoNeutral($dom, $defs, $scope),
        'postits' => effectsDemoPostits($dom, $root, $defs, $scope),
        'blueprint' => effectsDemoBlueprint($dom, $root, $defs, $scope),
        'sketch' => effectsDemoSketch($dom, $root, $defs, $scope),
        'metro' => effectsDemoMetro($dom, $root, $defs, $scope),
        default => null,
    };
    effectsDemoFlattenKanbanColumns($root);

    $html = $dom->saveXML($root);
    if (false === $html) {
        throw new RuntimeException('Cannot serialize effect SVG.');
    }

    return $html;
}

function effectsDemoDom(string $svg): DOMDocument
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    if (!$dom->loadXML($svg)) {
        throw new RuntimeException('Cannot parse rendered SVG.');
    }

    return $dom;
}

/**
 * @param array<string, string> $attributes
 */
function effectsDemoElement(DOMDocument $dom, string $name, array $attributes = []): DOMElement
{
    $element = $dom->createElementNS('http://www.w3.org/2000/svg', $name);
    foreach ($attributes as $key => $value) {
        $element->setAttribute($key, $value);
    }

    return $element;
}

function effectsDemoMono(DOMDocument $dom, DOMElement $defs, string $scope): void
{
    effectsDemoStyle($dom, $defs, $scope, <<<'CSS'
        & {
          --adi-bg: transparent;
          --adi-edge: #111111;
          --adi-arrow: #111111;
          --adi-node-fill: transparent;
          --adi-node-stroke: #111111;
          --adi-node-stroke-width: 1.6;
          --adi-text: #111111;
          --adi-muted-text: #4b5563;
          --adi-label-bg: #ffffff;
          --adi-label-outline: #ffffff;
        }
        & .adi-background { fill: transparent; fill: var(--adi-bg); }
        & .adi-edge { fill: none; stroke: #111111; stroke: var(--adi-edge); stroke-width: 1.6; stroke-width: var(--adi-node-stroke-width); stroke-linecap: round; stroke-linejoin: round; }
        & .adi-arrowhead, & .adi-terminal-fill { fill: #111111; fill: var(--adi-arrow); }
        & .adi-node, & .adi-terminal-ring { fill: transparent; fill: var(--adi-node-fill); stroke: #111111; stroke: var(--adi-node-stroke); stroke-width: 1.6; stroke-width: var(--adi-node-stroke-width); }
        & .adi-cluster { fill: transparent; stroke: var(--adi-node-stroke); stroke-width: 1.1; opacity: 0.5; }
        & .adi-cluster-header { fill: transparent; stroke: none; opacity: 1; }
        & .adi-activation { fill: var(--adi-edge); stroke: none; }
        & .adi-venn-circle { fill: transparent; stroke: var(--adi-node-stroke); stroke-width: var(--adi-node-stroke-width); opacity: 0.58; }
        & .adi-label-halo { fill: #ffffff; stroke: none; }
        & .adi-title, & .adi-node-label { fill: #111111; }
        & .adi-edge-label { fill: #1f2937; stroke: #ffffff; }
    CSS);
}

function effectsDemoWhiteBlack(DOMDocument $dom, DOMElement $root, DOMElement $defs, string $scope): void
{
    effectsDemoStyle($dom, $defs, $scope, <<<'CSS'
        & {
          --adi-bg: #030303;
          --adi-edge: #f8fafc;
          --adi-arrow: #f8fafc;
          --adi-node-fill: #030303;
          --adi-node-stroke: #f8fafc;
          --adi-node-stroke-width: 1.7;
          --adi-text: #ffffff;
          --adi-muted-text: #d1d5db;
          --adi-label-bg: #030303;
          --adi-label-outline: #030303;
        }
        & .adi-background { fill: #030303; fill: var(--adi-bg); }
        & .adi-edge { fill: none; stroke: #f8fafc; stroke: var(--adi-edge); stroke-width: 1.7; stroke-width: var(--adi-node-stroke-width); stroke-linecap: round; stroke-linejoin: round; }
        & .adi-arrowhead, & .adi-terminal-fill { fill: #f8fafc; fill: var(--adi-arrow); }
        & .adi-node, & .adi-terminal-ring { fill: #030303; fill: var(--adi-node-fill); stroke: #f8fafc; stroke: var(--adi-node-stroke); stroke-width: 1.7; stroke-width: var(--adi-node-stroke-width); }
        & .adi-cluster { fill: #030303; stroke: var(--adi-node-stroke); stroke-width: 1.1; opacity: 0.72; }
        & .adi-cluster-header { fill: #030303; stroke: none; opacity: 1; }
        & .adi-activation { fill: var(--adi-edge); stroke: none; }
        & .adi-venn-circle { fill: transparent; stroke: var(--adi-node-stroke); stroke-width: var(--adi-node-stroke-width); opacity: 0.64; }
        & .adi-label-halo { fill: #030303; stroke: none; }
        & .adi-title, & .adi-node-label { fill: #ffffff; }
        & .adi-edge-label { fill: #f1f5f9; stroke: #030303; }
    CSS);

    foreach (effectsDemoElements($root, 'rect') as $index => $element) {
        if (0 === $index) {
            continue;
        }

        $element->setAttribute('rx', '7');
        $element->setAttribute('ry', '7');
    }
}

function effectsDemoNeutral(DOMDocument $dom, DOMElement $defs, string $scope): void
{
    effectsDemoStyle($dom, $defs, $scope, <<<'CSS'
        & {
          --adi-bg: #f5f3ef;
          --adi-edge: #d8d2c8;
          --adi-arrow: #d8d2c8;
          --adi-node-fill: #ddd7cc;
          --adi-node-fill-alt: #e8e3da;
          --adi-node-stroke: #d6d0c6;
          --adi-node-stroke-width: 0.3;
          --adi-text: #201f1c;
          --adi-muted-text: #706a60;
          --adi-label-bg: #f5f3ef;
          --adi-label-outline: #f5f3ef;
        }
        & .adi-background { fill: #f5f3ef; fill: var(--adi-bg); }
        & .adi-edge { fill: none; stroke: #b9b2a8; stroke-width: 0.75; stroke-linecap: round; stroke-linejoin: round; }
        & .adi-arrowhead, & .adi-terminal-fill { fill: #b9b2a8; fill: var(--adi-arrow); }
        & .adi-node, & .adi-terminal-ring { fill: #ddd7cc; fill: var(--adi-node-fill); stroke: #d6d0c6; stroke: var(--adi-node-stroke); stroke-width: 0.3; stroke-width: var(--adi-node-stroke-width); }
        & .adi-node-alt { fill: #e8e3da; fill: var(--adi-node-fill-alt); }
        & .adi-cluster { fill: #ebe6dd; stroke: #d3ccc1; stroke-width: 0.4; opacity: 0.82; }
        & .adi-cluster-header { fill: #ded8ce; stroke: none; opacity: 0.72; }
        & .adi-activation { fill: #c9c1b4; stroke: none; }
        & .adi-venn-circle { fill: #ded8ce; stroke: #b9b2a8; stroke-width: 0.6; opacity: 0.48; }
        & .adi-label-halo { fill: #f5f3ef; stroke: none; }
        & .adi-title, & .adi-node-label { fill: #201f1c; }
        & .adi-edge-label { fill: #4f463d; stroke: #f5f3ef; }
    CSS);
}

function effectsDemoPostits(DOMDocument $dom, DOMElement $root, DOMElement $defs, string $scope): void
{
    $filterId = effectsDemoScopedId($scope, 'postit-marker-line');
    $filter = effectsDemoElement($dom, 'filter', [
        'id' => $filterId,
        'x' => '-8%',
        'y' => '-8%',
        'width' => '116%',
        'height' => '116%',
    ]);
    $filter->appendChild(effectsDemoElement($dom, 'feTurbulence', [
        'type' => 'fractalNoise',
        'baseFrequency' => '0.04',
        'numOctaves' => '1',
        'seed' => '29',
        'result' => 'noise',
    ]));
    $filter->appendChild(effectsDemoElement($dom, 'feDisplacementMap', [
        'in' => 'SourceGraphic',
        'in2' => 'noise',
        'scale' => '0.45',
        'xChannelSelector' => 'R',
        'yChannelSelector' => 'G',
    ]));
    $defs->appendChild($filter);

    effectsDemoStyle($dom, $defs, $scope, sprintf(<<<'CSS'
        & {
          --adi-bg: #f6ecd8;
          --adi-edge: #2f2720;
          --adi-arrow: #2f2720;
          --adi-node-stroke: rgba(47, 39, 32, 0.18);
          --adi-node-stroke-width: 0.8;
          --adi-text: #201813;
          --adi-muted-text: #5c4635;
          --adi-label-bg: #f6ecd8;
          --adi-label-outline: #f6ecd8;
          --postit-yellow: #fff176;
          --postit-orange: #ffcc80;
          --postit-green: #a7f3d0;
          --postit-blue: #bfdbfe;
          --postit-pink: #fbcfe8;
        }
        & .adi-background { fill: var(--adi-bg); }
        & .adi-edge { fill: none; stroke: var(--adi-edge); stroke-width: 3.4; stroke-linecap: round; stroke-linejoin: round; opacity: 0.62; filter: url(#%s); }
        & .adi-arrowhead, & .adi-terminal-fill { fill: var(--adi-arrow); opacity: 0.9; filter: url(#%s); }
        & .adi-node { fill: var(--postit-yellow); stroke: var(--adi-node-stroke); stroke-width: var(--adi-node-stroke-width); filter: drop-shadow(0 5px 4px rgba(48, 36, 21, 0.2)); }
        & .adi-node-palette-2 { fill: var(--postit-orange); }
        & .adi-node-palette-3 { fill: var(--postit-green); }
        & .adi-node-palette-4 { fill: var(--postit-blue); }
        & .adi-node-palette-5 { fill: var(--postit-pink); }
        & .adi-postit-fold { fill: rgba(255, 255, 255, 0.38); stroke: rgba(47, 39, 32, 0.1); stroke-width: 0.6; }
        & .adi-terminal-ring { fill: var(--adi-bg); stroke: var(--adi-edge); stroke-width: 3.2; }
        & .adi-cluster { fill: rgba(255, 247, 222, 0.5); stroke: rgba(47, 39, 32, 0.5); stroke-width: 1.4; opacity: 1; }
        & .adi-cluster-header { fill: rgba(255, 241, 118, 0.34); stroke: none; }
        & .adi-activation { fill: rgba(47, 39, 32, 0.7); stroke: none; }
        & .adi-venn-circle { fill: rgba(255, 241, 118, 0.38); stroke: rgba(47, 39, 32, 0.55); stroke-width: 1.6; }
        & .adi-label-halo { fill: #fbf2df; stroke: none; }
        & .adi-title { fill: var(--adi-text); font-size: 28px; font-weight: 700; letter-spacing: 0; }
        & .adi-node-label { fill: var(--adi-text); font-size: 16.5px; font-weight: 700; }
        & .adi-edge-label { fill: var(--adi-muted-text); stroke: var(--adi-label-outline); }
    CSS, $filterId, $filterId));

    foreach (effectsDemoElements($root, 'text') as $element) {
        if (str_contains(' '.$element->getAttribute('class').' ', ' adi-title ')) {
            $element->setAttribute('y', '42');
            $element->setAttribute('font-size', '28');
        }
        if (str_contains(' '.$element->getAttribute('class').' ', ' adi-node-label ')) {
            $element->setAttribute('y', effectsDemoNumber((float) $element->getAttribute('y') + 0.8));
            $element->setAttribute('font-size', '16.5');
            $element->setAttribute('font-weight', '700');
        }
    }

    $nodeIndex = 0;
    foreach (effectsDemoElements($root, 'rect') as $element) {
        if (!str_contains(' '.$element->getAttribute('class').' ', ' adi-node ')) {
            continue;
        }
        $x = (float) $element->getAttribute('x');
        $y = (float) $element->getAttribute('y');
        $width = (float) $element->getAttribute('width');
        $height = (float) $element->getAttribute('height');
        $note = effectsDemoElement($dom, 'path', [
            'd' => effectsDemoPostitPath($x, $y, $width, $height, $nodeIndex),
            'class' => $element->getAttribute('class').' adi-postit-note',
            'fill' => effectsDemoPostitFill($nodeIndex),
            'stroke' => '#2f2720',
            'stroke-opacity' => '0.18',
            'stroke-width' => '0.8',
            'filter' => 'url(#'.$filterId.')',
        ]);
        $fold = effectsDemoElement($dom, 'path', [
            'd' => effectsDemoPostitFoldPath($x, $y, $width, $nodeIndex),
            'class' => 'adi-postit-fold',
            'fill' => '#fff8b5',
            'stroke' => '#2f2720',
            'stroke-opacity' => '0.1',
            'stroke-width' => '0.6',
        ]);

        $parent = $element->parentNode;
        if ($parent instanceof DOMNode) {
            $parent->insertBefore($note, $element);
            $parent->insertBefore($fold, $element);
            $parent->removeChild($element);
        }
        ++$nodeIndex;
    }
}

function effectsDemoBlueprint(DOMDocument $dom, DOMElement $root, DOMElement $defs, string $scope): void
{
    $gridId = effectsDemoScopedId($scope, 'blueprint-grid');
    $majorGridId = effectsDemoScopedId($scope, 'blueprint-major-grid');
    $grid = effectsDemoElement($dom, 'pattern', [
        'id' => $gridId,
        'width' => '16',
        'height' => '16',
        'patternUnits' => 'userSpaceOnUse',
    ]);
    $grid->appendChild(effectsDemoElement($dom, 'path', [
        'd' => 'M 16 0 H 0 V 16',
        'fill' => 'none',
        'stroke' => '#67e8f9',
        'stroke-width' => '0.45',
        'opacity' => '0.26',
    ]));
    $defs->appendChild($grid);

    $major = effectsDemoElement($dom, 'pattern', [
        'id' => $majorGridId,
        'width' => '80',
        'height' => '80',
        'patternUnits' => 'userSpaceOnUse',
    ]);
    $major->appendChild(effectsDemoElement($dom, 'path', [
        'd' => 'M 80 0 H 0 V 80',
        'fill' => 'none',
        'stroke' => '#e6fbff',
        'stroke-width' => '0.8',
        'opacity' => '0.22',
    ]));
    $defs->appendChild($major);

    effectsDemoInsertGridLayer($dom, $root, $gridId, '0.78');
    effectsDemoInsertGridLayer($dom, $root, $majorGridId, '0.72');
    effectsDemoStyle($dom, $defs, $scope, <<<'CSS'
        & {
          --adi-bg: #06182b;
          --adi-edge: #bff7ff;
          --adi-arrow: #e6fbff;
          --adi-node-fill: #08243d;
          --adi-node-stroke: #e6fbff;
          --adi-text: #ffffff;
          --adi-muted-text: #bae6fd;
          --adi-label-bg: #06182b;
          --adi-label-outline: #06182b;
        }
        & .adi-background { fill: #06182b; fill: var(--adi-bg); }
        & .adi-edge { fill: none; stroke: #bff7ff; stroke: var(--adi-edge); stroke-width: 0.85; stroke-linecap: round; stroke-linejoin: round; }
        & .adi-arrowhead, & .adi-terminal-fill { fill: #e6fbff; fill: var(--adi-arrow); }
        & .adi-node, & .adi-terminal-ring { fill: #08243d; fill: var(--adi-node-fill); stroke: #e6fbff; stroke: var(--adi-node-stroke); stroke-width: 1.45; }
        & .adi-node-alt { stroke-width: 0.85; }
        & .adi-cluster { fill: rgba(8, 36, 61, 0.5); stroke: rgba(230, 251, 255, 0.58); stroke-width: 0.85; opacity: 1; }
        & .adi-cluster-header { fill: rgba(103, 232, 249, 0.11); stroke: none; }
        & .adi-activation { fill: #67e8f9; stroke: none; }
        & .adi-venn-circle { fill: rgba(56, 189, 248, 0.14); stroke: #e6fbff; stroke-width: 1.0; opacity: 0.68; }
        & .adi-label-halo { fill: #06182b; fill: var(--adi-label-bg); stroke: none; }
        & .adi-title, & .adi-node-label { fill: #ffffff; fill: var(--adi-text); }
        & .adi-edge-label { fill: #bae6fd; fill: var(--adi-muted-text); stroke: #06182b; stroke: var(--adi-label-outline); }
    CSS);

    foreach (effectsDemoElements($root, 'rect') as $index => $element) {
        if (0 === $index || 'url(#'.$gridId.')' === $element->getAttribute('fill') || 'url(#'.$majorGridId.')' === $element->getAttribute('fill')) {
            continue;
        }

        $height = (float) $element->getAttribute('height');
        if ($height > 28.0) {
            $element->setAttribute('rx', '5');
            $element->setAttribute('ry', '5');
        }
    }

    $edgeIndex = 0;
    foreach (effectsDemoStrokeElements($root) as $element) {
        if (!str_contains(' '.$element->getAttribute('class').' ', ' adi-edge ')) {
            continue;
        }
        if (1 === $edgeIndex % 3) {
            $element->setAttribute('stroke-dasharray', '6 5');
        } elseif (2 === $edgeIndex % 3) {
            $element->setAttribute('stroke-dasharray', '1 5');
            $element->setAttribute('stroke-linecap', 'round');
        }
        ++$edgeIndex;
    }
}

function effectsDemoSketch(DOMDocument $dom, DOMElement $root, DOMElement $defs, string $scope): void
{
    $filterId = effectsDemoScopedId($scope, 'sketch-rough-line');
    $filter = effectsDemoElement($dom, 'filter', [
        'id' => $filterId,
        'x' => '-4%',
        'y' => '-4%',
        'width' => '108%',
        'height' => '108%',
    ]);
    $filter->appendChild(effectsDemoElement($dom, 'feTurbulence', [
        'type' => 'fractalNoise',
        'baseFrequency' => '0.026',
        'numOctaves' => '2',
        'seed' => '17',
        'result' => 'noise',
    ]));
    $filter->appendChild(effectsDemoElement($dom, 'feDisplacementMap', [
        'in' => 'SourceGraphic',
        'in2' => 'noise',
        'scale' => '0.72',
        'xChannelSelector' => 'R',
        'yChannelSelector' => 'G',
    ]));
    $defs->appendChild($filter);

    effectsDemoStyle($dom, $defs, $scope, sprintf(<<<'CSS'
        & {
          --adi-bg: #fbf6ec;
          --adi-edge: #2d2821;
          --adi-arrow: #2d2821;
          --adi-node-fill: #fffaf0;
          --adi-node-fill-alt: #f7eddd;
          --adi-node-stroke: #2d2821;
          --adi-node-stroke-width: 1.7;
          --adi-text: #211d18;
          --adi-muted-text: #746a5d;
          --adi-label-bg: #fbf6ec;
          --adi-label-outline: #fbf6ec;
          background: #fbf6ec;
        }
        & .adi-background { fill: #fbf6ec; }
        & .adi-edge { fill: none; stroke: #2d2821; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; filter: url(#%s); }
        & .adi-arrowhead, & .adi-terminal-fill { fill: #2d2821; filter: url(#%s); }
        & .adi-node, & .adi-terminal-ring { fill: #fffaf0; stroke: #2d2821; stroke-width: 1.7; filter: url(#%s); }
        & .adi-node-alt { fill: #f7eddd; }
        & .adi-node-palette-3, & .adi-node-palette-4, & .adi-node-palette-5 { fill: #fbf2e4; }
        & .adi-cluster { fill: rgba(255, 250, 240, 0.58); stroke: rgba(45, 40, 33, 0.62); stroke-width: 1.1; opacity: 1; filter: url(#%s); }
        & .adi-cluster-header { fill: rgba(45, 40, 33, 0.08); stroke: none; }
        & .adi-activation { fill: rgba(45, 40, 33, 0.62); stroke: none; filter: url(#%s); }
        & .adi-venn-circle { fill: rgba(122, 78, 61, 0.12); stroke: #2d2821; stroke-width: 1.5; opacity: 0.72; filter: url(#%s); }
        & .adi-label-halo { fill: #e8dcc9; opacity: 0.55; }
        & .adi-title, & .adi-node-label { fill: #211d18; }
        & .adi-edge-label { fill: #3f362d; stroke: none; }
    CSS, $filterId, $filterId, $filterId, $filterId, $filterId, $filterId));

    effectsDemoNormalizeSketchAttributes($root, $filterId);
}

function effectsDemoNormalizeSketchAttributes(DOMElement $root, string $filterId): void
{
    $filter = 'url(#'.$filterId.')';
    $fills = ['#fffaf0', '#f7eddd', '#fbf2e4', '#f4ead9', '#fff6e5'];

    foreach (effectsDemoElements($root, 'rect') as $element) {
        if (effectsDemoHasClass($element, 'adi-background')) {
            $element->setAttribute('fill', '#fbf6ec');

            continue;
        }

        if (effectsDemoHasClass($element, 'adi-label-halo')) {
            $element->setAttribute('fill', '#e8dcc9');
            $element->setAttribute('stroke', 'none');
            $element->setAttribute('opacity', '0.55');

            continue;
        }

        if (effectsDemoHasClass($element, 'adi-activation')) {
            $element->setAttribute('fill', '#6a5d50');
            $element->setAttribute('stroke', 'none');
            $element->setAttribute('opacity', '0.72');

            continue;
        }

        if (effectsDemoHasClass($element, 'adi-cluster')) {
            $element->setAttribute('fill', effectsDemoHasClass($element, 'adi-cluster-header') ? '#efe3d0' : '#fff6e8');
            $element->setAttribute('stroke', effectsDemoHasClass($element, 'adi-cluster-header') ? 'none' : '#6a5d50');
            $element->setAttribute('stroke-width', effectsDemoHasClass($element, 'adi-cluster-header') ? '0' : '1.1');
            $element->setAttribute('opacity', effectsDemoHasClass($element, 'adi-cluster-header') ? '0.62' : '0.58');
            $element->setAttribute('filter', $filter);

            continue;
        }

        if (effectsDemoHasClass($element, 'adi-node')) {
            $palette = 0;
            for ($index = 1; $index <= 5; ++$index) {
                if (effectsDemoHasClass($element, 'adi-node-palette-'.$index)) {
                    $palette = $index - 1;
                    break;
                }
            }
            $element->setAttribute('fill', $fills[$palette]);
            $element->setAttribute('stroke', '#2d2821');
            $element->setAttribute('stroke-width', '1.7');
            $element->setAttribute('filter', $filter);
        }
    }

    foreach (effectsDemoElements($root, 'text') as $element) {
        if (effectsDemoHasClass($element, 'adi-edge-label')) {
            $element->setAttribute('fill', '#3f362d');
        } else {
            $element->setAttribute('fill', '#211d18');
        }
        $element->setAttribute('stroke', 'none');
    }

    foreach (effectsDemoElements($root, 'line') as $element) {
        $element->setAttribute('fill', 'none');
        $element->setAttribute('stroke', '#2d2821');
        $element->setAttribute('filter', $filter);
    }

    foreach (effectsDemoElements($root, 'path') as $element) {
        if (effectsDemoHasClass($element, 'adi-edge')) {
            $element->setAttribute('fill', 'none');
            $element->setAttribute('stroke', '#2d2821');
        } elseif (effectsDemoHasClass($element, 'adi-arrowhead')) {
            $element->setAttribute('fill', '#2d2821');
            $element->setAttribute('stroke', 'none');
        }
        $element->setAttribute('filter', $filter);
    }

    foreach (effectsDemoElements($root, 'circle') as $element) {
        if (effectsDemoHasClass($element, 'adi-terminal-fill')) {
            $element->setAttribute('fill', '#2d2821');
            $element->setAttribute('stroke', 'none');
        } elseif (effectsDemoHasClass($element, 'adi-terminal-ring')) {
            $element->setAttribute('fill', '#fbf6ec');
            $element->setAttribute('stroke', '#2d2821');
            $element->setAttribute('stroke-width', '1.7');
        } elseif (effectsDemoHasClass($element, 'adi-venn-circle')) {
            $element->setAttribute('fill', '#eadbc3');
            $element->setAttribute('stroke', '#2d2821');
            $element->setAttribute('stroke-width', '1.5');
            $element->setAttribute('opacity', '0.5');
        }
        $element->setAttribute('filter', $filter);
    }
}

function effectsDemoMetro(DOMDocument $dom, DOMElement $root, DOMElement $defs, string $scope): void
{
    $gradientId = effectsDemoScopedId($scope, 'metro-stroke');
    $filterId = effectsDemoScopedId($scope, 'metro-bevel');
    $gradient = effectsDemoElement($dom, 'linearGradient', [
        'id' => $gradientId,
        'x1' => '0%',
        'y1' => '0%',
        'x2' => '100%',
        'y2' => '100%',
    ]);
    foreach ([
        ['0%', '#38bdf8'],
        ['42%', '#34d399'],
        ['72%', '#facc15'],
        ['100%', '#f472b6'],
    ] as [$offset, $color]) {
        $gradient->appendChild(effectsDemoElement($dom, 'stop', [
            'offset' => $offset,
            'stop-color' => $color,
        ]));
    }
    $defs->appendChild($gradient);

    $filter = effectsDemoElement($dom, 'filter', [
        'id' => $filterId,
        'x' => '-20%',
        'y' => '-20%',
        'width' => '140%',
        'height' => '140%',
        'color-interpolation-filters' => 'sRGB',
    ]);
    $filter->appendChild(effectsDemoElement($dom, 'feDropShadow', [
        'dx' => '0',
        'dy' => '1',
        'stdDeviation' => '0.7',
        'flood-color' => '#ffffff',
        'flood-opacity' => '0.34',
    ]));
    $filter->appendChild(effectsDemoElement($dom, 'feDropShadow', [
        'dx' => '0',
        'dy' => '2',
        'stdDeviation' => '1.1',
        'flood-color' => '#020617',
        'flood-opacity' => '0.65',
    ]));
    $defs->appendChild($filter);
    effectsDemoStyle($dom, $defs, $scope, sprintf(<<<'CSS'
        & {
          --adi-bg: #07111f;
          --adi-edge: url(#%s);
          --adi-arrow: #67e8f9;
          --adi-node-fill: #0d1b2d;
          --adi-node-fill-alt: #10263d;
          --adi-node-stroke: #67e8f9;
          --adi-node-stroke-width: 2.35;
          --adi-text: #f8fafc;
          --adi-muted-text: #bfdbfe;
          --adi-label-bg: #07111f;
          --adi-label-outline: #07111f;
        }
        & .adi-background { fill: #07111f; }
        & .adi-edge { fill: none; stroke: url(#%s); stroke-width: 3.2; stroke-linecap: round; stroke-linejoin: round; filter: url(#%s); }
        & .adi-arrowhead, & .adi-terminal-fill { fill: #67e8f9; filter: url(#%s); }
        & .adi-node, & .adi-terminal-ring { fill: #0d1b2d; stroke: #67e8f9; stroke-width: 2.35; }
        & .adi-node-alt { fill: #10263d; }
        & .adi-node-palette-3, & .adi-node-palette-4, & .adi-node-palette-5 { fill: #0f2135; }
        & .adi-cluster { fill: rgba(13, 27, 45, 0.78); stroke: rgba(103, 232, 249, 0.72); stroke-width: 1.25; opacity: 1; }
        & .adi-cluster-header { fill: rgba(103, 232, 249, 0.11); stroke: none; opacity: 1; }
        & .adi-activation { fill: #67e8f9; stroke: none; filter: url(#%s); }
        & .adi-venn-circle { fill: rgba(56, 189, 248, 0.22); stroke: #67e8f9; stroke-width: 2.2; opacity: 0.78; }
        & .adi-label-halo { fill: #07111f; }
        & .adi-title, & .adi-node-label { fill: #f8fafc; }
        & .adi-edge-label { fill: #dbeafe; stroke: #07111f; }
        & .adi-edge-underlay { fill: none; stroke: #020617; opacity: 0.58; stroke-linecap: round; stroke-linejoin: round; }
    CSS, $gradientId, $gradientId, $filterId, $filterId, $filterId));

    foreach (effectsDemoStrokeElements($root) as $element) {
        effectsDemoInsertUnderlay($element, 5.0);
    }

    foreach (effectsDemoElements($root, 'rect') as $index => $element) {
        if (0 === $index) {
            continue;
        }

        $element->setAttribute('rx', '10');
        $element->setAttribute('ry', '10');
    }
}

function effectsDemoInsertGridLayer(DOMDocument $dom, DOMElement $root, string $patternId, string $opacity): void
{
    $rect = effectsDemoElement($dom, 'rect', [
        'x' => '0',
        'y' => '0',
        'width' => $root->getAttribute('width'),
        'height' => $root->getAttribute('height'),
        'fill' => 'url(#'.$patternId.')',
        'opacity' => $opacity,
    ]);

    $background = effectsDemoFirstElement($root, 'rect');
    if (null !== $background && null !== $background->nextSibling) {
        $root->insertBefore($rect, $background->nextSibling);

        return;
    }

    $root->appendChild($rect);
}

function effectsDemoPostitPath(float $x, float $y, float $width, float $height, int $index): string
{
    $tilt = [1.2, -0.8, 0.6, -1.1, 0.9][$index % 5];
    $fold = min(18.0, max(13.0, $width * 0.16));
    $right = $x + $width;
    $bottom = $y + $height;

    return sprintf(
        'M %s %s L %s %s L %s %s L %s %s L %s %s L %s %s Z',
        effectsDemoNumber($x + 1.5),
        effectsDemoNumber($y + 2.0 + $tilt),
        effectsDemoNumber($right - $fold),
        effectsDemoNumber($y + 0.8 - $tilt),
        effectsDemoNumber($right - 0.8),
        effectsDemoNumber($y + $fold + 0.6),
        effectsDemoNumber($right - 1.6),
        effectsDemoNumber($bottom - 1.2),
        effectsDemoNumber($x + 2.5),
        effectsDemoNumber($bottom + 0.8 - $tilt),
        effectsDemoNumber($x + 0.4),
        effectsDemoNumber($y + 2.0 + $tilt),
    );
}

function effectsDemoPostitFoldPath(float $x, float $y, float $width, int $index): string
{
    $tilt = [1.2, -0.8, 0.6, -1.1, 0.9][$index % 5];
    $fold = min(18.0, max(13.0, $width * 0.16));
    $right = $x + $width;

    return sprintf(
        'M %s %s L %s %s L %s %s Z',
        effectsDemoNumber($right - $fold),
        effectsDemoNumber($y + 0.8 - $tilt),
        effectsDemoNumber($right - 0.8),
        effectsDemoNumber($y + $fold + 0.6),
        effectsDemoNumber($right - $fold + 1.6),
        effectsDemoNumber($y + $fold + 1.6),
    );
}

function effectsDemoPostitFill(int $index): string
{
    return ['#fff176', '#ffcc80', '#a7f3d0', '#bfdbfe', '#fbcfe8'][$index % 5];
}

function effectsDemoInsertUnderlay(DOMElement $element, float $extraWidth): void
{
    $parent = $element->parentNode;
    if (!$parent instanceof DOMNode) {
        return;
    }

    $underlay = $element->cloneNode(false);
    if (!$underlay instanceof DOMElement) {
        return;
    }

    $strokeWidth = (float) ($element->getAttribute('stroke-width') ?: '1');
    $underlay->setAttribute('stroke-width', effectsDemoNumber($strokeWidth + $extraWidth));
    $underlay->setAttribute('class', 'adi-edge-underlay');
    $parent->insertBefore($underlay, $element);
}

function effectsDemoClassifySvg(DOMElement $root): void
{
    $isGit = str_contains(' '.$root->getAttribute('class').' ', ' adi-diagram-git ');
    $isVenn = str_contains(' '.$root->getAttribute('class').' ', ' adi-diagram-venn ');
    $rectIndex = 0;
    $nodeIndex = 0;
    foreach (effectsDemoElements($root, 'rect') as $element) {
        if (0 === $rectIndex++) {
            effectsDemoAddClass($element, 'adi-background');

            continue;
        }

        if ((float) $element->getAttribute('width') <= 16.0 && (float) $element->getAttribute('height') > 28.0) {
            effectsDemoAddClass($element, 'adi-activation');

            continue;
        }

        if ('' !== $element->getAttribute('opacity')) {
            effectsDemoAddClass($element, 'adi-cluster');
            if ((float) $element->getAttribute('height') <= 32.0) {
                effectsDemoAddClass($element, 'adi-cluster-header');
            }

            continue;
        }

        if ((float) $element->getAttribute('height') <= 28.0) {
            effectsDemoAddClass($element, 'adi-label-halo');

            continue;
        }

        if ($isGit || $isVenn) {
            effectsDemoAddClass($element, 'adi-label-halo');

            continue;
        }

        effectsDemoAddClass($element, 'adi-node');
        effectsDemoAddClass($element, 'adi-node-palette-'.($nodeIndex % 5 + 1));
        if (1 === $nodeIndex % 2) {
            effectsDemoAddClass($element, 'adi-node-alt');
        }
        ++$nodeIndex;
    }

    foreach (effectsDemoElements($root, 'line') as $element) {
        effectsDemoAddClass($element, 'adi-edge');
    }

    foreach (effectsDemoElements($root, 'path') as $element) {
        effectsDemoAddClass($element, 'none' === $element->getAttribute('fill') ? 'adi-edge' : 'adi-arrowhead');
    }

    foreach (effectsDemoElements($root, 'circle') as $element) {
        if ($isVenn && (float) $element->getAttribute('r') > 20.0) {
            effectsDemoAddClass($element, 'adi-node');
            effectsDemoAddClass($element, 'adi-venn-circle');

            continue;
        }

        effectsDemoAddClass($element, '' === $element->getAttribute('stroke') ? 'adi-terminal-fill' : 'adi-terminal-ring');
    }

    $textIndex = 0;
    foreach (effectsDemoElements($root, 'text') as $element) {
        $fontSize = (float) $element->getAttribute('font-size');
        if (0 === $textIndex++) {
            effectsDemoAddClass($element, 'adi-title');

            continue;
        }

        effectsDemoAddClass($element, $fontSize < 13.0 ? 'adi-edge-label' : 'adi-node-label');
    }
}

function effectsDemoFlattenKanbanColumns(DOMElement $root): void
{
    if (!effectsDemoHasClass($root, 'adi-diagram-kanban')) {
        return;
    }

    foreach (effectsDemoElements($root, 'rect') as $element) {
        if (!effectsDemoHasClass($element, 'adi-cluster')) {
            continue;
        }

        $element->setAttribute('style', 'fill: transparent; stroke: none; filter: none; opacity: 1;');
    }
}

function effectsDemoAddClass(DOMElement $element, string $class): void
{
    $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    if (!\in_array($class, $classes, true)) {
        $classes[] = $class;
    }
    $element->setAttribute('class', trim(implode(' ', $classes)));
}

function effectsDemoHasClass(DOMElement $element, string $class): bool
{
    return str_contains(' '.$element->getAttribute('class').' ', ' '.$class.' ');
}

function effectsDemoStyle(DOMDocument $dom, DOMElement $defs, string $scope, string $css): void
{
    $style = effectsDemoElement($dom, 'style');
    $style->appendChild($dom->createCDATASection("\n".str_replace('&', 'svg.'.$scope, $css)."\n"));
    $defs->appendChild($style);
}

/**
 * @return list<DOMElement>
 */
function effectsDemoStrokeElements(DOMElement $root): array
{
    $elements = [];
    foreach (['line', 'path'] as $tagName) {
        foreach (effectsDemoElements($root, $tagName) as $element) {
            if ('path' === $tagName && 'none' !== $element->getAttribute('fill')) {
                continue;
            }
            $elements[] = $element;
        }
    }

    return $elements;
}

/**
 * @return list<DOMElement>
 */
function effectsDemoElements(DOMElement $root, string $tagName): array
{
    $elements = [];
    foreach ($root->getElementsByTagName($tagName) as $element) {
        if ($element instanceof DOMElement) {
            if (effectsDemoIsInsideDefs($element)) {
                continue;
            }
            $elements[] = $element;
        }
    }

    return $elements;
}

function effectsDemoFirstElement(DOMElement $root, string $tagName): ?DOMElement
{
    foreach ($root->getElementsByTagName($tagName) as $element) {
        if ($element instanceof DOMElement) {
            return $element;
        }
    }

    return null;
}

function effectsDemoIsInsideDefs(DOMElement $element): bool
{
    $node = $element->parentNode;
    while ($node instanceof DOMElement) {
        if ('defs' === $node->tagName) {
            return true;
        }
        $node = $node->parentNode;
    }

    return false;
}

/**
 * @param list<array{slug: string, name: string, caption: string, media: string}> $variants
 */
function effectsDemoHtml(array $variants): string
{
    $cards = [];
    foreach ($variants as $index => $variant) {
        $cards[] = sprintf(
            <<<'HTML'
            <article class="effect-card effect-%s" data-gallery-card data-index="%d" tabindex="0" role="button" aria-pressed="false">
              <div class="effect-media">%s</div>
              <div class="effect-copy">
                <h2>%s</h2>
                <p>%s</p>
              </div>
            </article>
            HTML,
            effectsDemoEscape($variant['slug']),
            $index,
            $variant['media'],
            effectsDemoEscape($variant['name']),
            effectsDemoEscape($variant['caption']),
        );
    }

    return strtr(
        <<<'HTML'
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Atelier Diagram Effects Demo</title>
          <style>
            :root {
              color-scheme: dark;
              --page: #090b12;
              --panel: #121622;
              --line: rgba(255, 255, 255, 0.12);
              --text: #f8fafc;
              --muted: #9aa7bb;
            }

            * {
              box-sizing: border-box;
            }

            body {
              margin: 0;
              min-height: 100vh;
              background: var(--page);
              color: var(--text);
              font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            main {
              width: min(1500px, calc(100vw - 48px));
              margin: 0 auto;
              padding: 38px 0 48px;
            }

            header {
              display: grid;
              grid-template-columns: minmax(0, 1fr) auto;
              gap: 24px;
              align-items: end;
              margin-bottom: 24px;
            }

            h1,
            h2,
            p {
              margin: 0;
            }

            h1 {
              font-size: clamp(28px, 4vw, 54px);
              line-height: 0.95;
              letter-spacing: 0;
            }

            header p {
              max-width: 760px;
              color: var(--muted);
              font-size: 15px;
              line-height: 1.55;
              margin-top: 14px;
            }

            .meta {
              color: #cbd5e1;
              font-size: 12px;
              font-weight: 700;
              letter-spacing: 0;
              text-transform: uppercase;
              white-space: nowrap;
            }

            .grid {
              display: grid;
              grid-template-columns: repeat(2, minmax(0, 1fr));
              gap: 32px;
              align-items: start;
              transition: grid-template-columns 180ms ease;
            }

            .effect-card {
              min-width: 0;
              border: 1px solid var(--line);
              background: rgba(18, 22, 34, 0.84);
              border-radius: 8px;
              overflow: hidden;
              cursor: pointer;
              outline: none;
              transition: border-color 160ms ease, opacity 160ms ease, transform 160ms ease;
            }

            .effect-card:focus-visible {
              border-color: rgba(255, 255, 255, 0.48);
            }

            .effect-media {
              min-height: 1120px;
              display: grid;
              place-items: center;
              padding: 24px;
              background: #050713;
            }

            .effect-card.is-selected .effect-media {
              cursor: grab;
              overflow: hidden;
              touch-action: none;
              user-select: none;
            }

            .effect-card.is-selected .effect-media.is-panning {
              cursor: grabbing;
            }

            .effect-mono-transparent .effect-media {
              background: #ffffff;
            }

            .effect-white-black .effect-media {
              background: #030303;
            }

            .effect-neutral-fill .effect-media {
              background: #f5f3ef;
            }

            .effect-post-its .effect-media {
              background: #f6ecd8;
            }

            .effect-blueprint .effect-media {
              background: #06182b;
            }

            .effect-warm-sketch .effect-media {
              background: #fbf6ec;
            }

            .effect-metro-light .effect-media {
              background: #07111f;
            }

            .effect-media svg {
              width: 100%;
              max-width: 700px;
              height: auto;
              display: block;
            }

            .diagram-pair {
              width: 100%;
              display: grid;
              grid-template-columns: repeat(4, minmax(0, 1fr));
              gap: 16px;
              align-items: center;
              transform-origin: center;
            }

            .diagram-panel {
              min-width: 0;
              margin: 0;
              display: grid;
              gap: 10px;
              align-items: center;
            }

            .diagram-surface {
              min-width: 0;
              display: grid;
              place-items: center;
            }

            .diagram-panel svg {
              max-width: 100%;
              max-height: 205px;
            }

            .diagram-panel--flowchart svg {
              max-width: 260px;
            }

            .diagram-panel--sequence svg {
              max-width: 360px;
            }

            .diagram-panel--git svg {
              max-width: 520px;
            }

            .diagram-panel--venn svg {
              max-width: 260px;
            }

            .diagram-panel--timeline svg {
              max-width: 620px;
              max-height: 270px;
            }

            .diagram-panel--timeline {
              grid-column: span 2;
            }

            .diagram-panel--block svg {
              max-width: 340px;
            }

            .diagram-panel--mindmap svg {
              max-width: 360px;
            }

            .diagram-panel--class svg {
              max-width: 300px;
            }

            .diagram-panel--er svg {
              max-width: 380px;
            }

            .diagram-panel--journey svg {
              max-width: 420px;
            }

            .diagram-panel--requirement svg {
              max-width: 360px;
            }

            .diagram-panel--kanban svg {
              max-width: 420px;
            }

            .diagram-panel--architecture svg {
              max-width: 440px;
            }

            .diagram-panel figcaption {
              color: rgba(248, 250, 252, 0.68);
              font-size: 12px;
              font-weight: 700;
              letter-spacing: 0;
              text-align: center;
              text-transform: uppercase;
            }

            .gallery-controls {
              position: fixed;
              right: 22px;
              bottom: 22px;
              display: none;
              grid-template-columns: repeat(2, 42px);
              gap: 8px;
              z-index: 10;
            }

            .viewport-controls {
              position: fixed;
              left: 22px;
              bottom: 22px;
              display: none;
              grid-template-columns: repeat(4, 42px);
              gap: 8px;
              z-index: 10;
            }

            .gallery-controls button {
              width: 42px;
              height: 42px;
              border: 1px solid rgba(255, 255, 255, 0.22);
              border-radius: 8px;
              background: rgba(9, 11, 18, 0.86);
              color: #f8fafc;
              font: 700 18px/1 system-ui, sans-serif;
              cursor: pointer;
            }

            .viewport-controls button {
              width: 42px;
              height: 42px;
              border: 1px solid rgba(255, 255, 255, 0.22);
              border-radius: 8px;
              background: rgba(9, 11, 18, 0.86);
              color: #f8fafc;
              font: 700 18px/1 system-ui, sans-serif;
              cursor: pointer;
            }

            .gallery-controls button:hover,
            .gallery-controls button:focus-visible,
            .viewport-controls button:hover,
            .viewport-controls button:focus-visible {
              border-color: rgba(255, 255, 255, 0.52);
              background: rgba(18, 22, 34, 0.96);
              outline: none;
            }

            body.gallery-open {
              overflow: hidden;
            }

            .grid.is-focus {
              grid-template-columns: minmax(0, 1fr) 190px;
              gap: 18px;
              height: calc(100vh - 134px);
              overflow: hidden;
            }

            .grid.is-focus .effect-card {
              grid-column: 2;
              border-color: rgba(255, 255, 255, 0.14);
              opacity: 0.72;
            }

            .grid.is-focus .effect-card:hover,
            .grid.is-focus .effect-card:focus-visible {
              opacity: 1;
            }

            .grid.is-focus .effect-card.is-selected {
              grid-column: 1;
              grid-row: 1 / span 7;
              cursor: default;
              opacity: 1;
              border-color: rgba(255, 255, 255, 0.3);
            }

            .grid.is-focus .effect-card:not(.is-selected) {
              max-height: 132px;
            }

            .grid.is-focus .effect-card:not(.is-selected) .effect-media {
              min-height: 92px;
              padding: 8px;
            }

            .grid.is-focus .effect-card:not(.is-selected) .effect-media svg {
              max-width: 170px;
            }

            .grid.is-focus .effect-card:not(.is-selected) .diagram-pair {
              grid-template-columns: 1fr;
              gap: 0;
            }

            .grid.is-focus .effect-card:not(.is-selected) .diagram-panel:not(.diagram-panel--state),
            .grid.is-focus .effect-card:not(.is-selected) .diagram-panel figcaption {
              display: none;
            }

            .grid.is-focus .effect-card:not(.is-selected) .effect-copy {
              padding: 8px 10px 10px;
            }

            .grid.is-focus .effect-card:not(.is-selected) .effect-copy h2 {
              font-size: 12px;
              line-height: 1.15;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            }

            .grid.is-focus .effect-card:not(.is-selected) .effect-copy p {
              display: none;
            }

            .grid.is-focus .effect-card.is-selected .effect-media {
              min-height: 80vh;
              height: 80vh;
              padding: 28px;
            }

            .grid.is-focus .effect-card.is-selected .effect-media svg {
              width: 100%;
              max-width: none;
              max-height: calc(80vh - 56px);
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel svg {
              max-height: calc((80vh - 212px) / 4);
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--flowchart svg {
              max-width: 300px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--sequence svg {
              max-width: 440px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--git svg {
              max-width: 650px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--venn svg {
              max-width: 310px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--timeline svg {
              max-width: 720px;
              max-height: calc((80vh - 184px) / 3);
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--block svg {
              max-width: 440px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--mindmap svg {
              max-width: 460px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--class svg {
              max-width: 380px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--er svg {
              max-width: 500px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--journey svg {
              max-width: 560px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--requirement svg {
              max-width: 460px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--kanban svg {
              max-width: 560px;
            }

            .grid.is-focus .effect-card.is-selected .diagram-panel--architecture svg {
              max-width: 580px;
            }

            body.gallery-open .gallery-controls {
              display: grid;
            }

            body.gallery-open .viewport-controls {
              display: grid;
            }

            .effect-card:fullscreen {
              width: 100vw;
              height: 100vh;
              border: 0;
              border-radius: 0;
              background: #090b12;
            }

            .effect-card:fullscreen .effect-media {
              height: 100vh;
              min-height: 100vh;
              padding: 32px;
            }

            .effect-card:fullscreen .effect-copy {
              display: none;
            }

            .effect-card:fullscreen .diagram-panel svg {
              max-height: calc((100vh - 220px) / 4);
            }

            .effect-copy {
              border-top: 1px solid var(--line);
              padding: 16px 18px 18px;
            }

            h2 {
              font-size: 18px;
              line-height: 1.2;
              letter-spacing: 0;
            }

            .effect-copy p {
              margin-top: 8px;
              color: var(--muted);
              font-size: 13px;
              line-height: 1.45;
            }

            @media (max-width: 900px) {
              main {
                width: min(100vw - 24px, 720px);
                padding-top: 24px;
              }

              header {
                grid-template-columns: 1fr;
              }

              .grid {
                grid-template-columns: 1fr;
              }

              .grid.is-focus {
                grid-template-columns: 1fr;
                height: auto;
                overflow: visible;
              }

              .grid.is-focus .effect-card {
                grid-column: auto;
              }

              .grid.is-focus .effect-card.is-selected {
                grid-column: auto;
                grid-row: auto;
              }

              .grid.is-focus .effect-card:not(.is-selected) {
                max-height: none;
              }

              .effect-media {
                min-height: 1180px;
                padding: 16px;
              }

              .diagram-pair {
                grid-template-columns: 1fr;
              }

              .diagram-panel svg,
              .diagram-panel--flowchart svg,
              .diagram-panel--sequence svg,
              .diagram-panel--git svg,
              .diagram-panel--venn svg,
              .diagram-panel--timeline svg,
              .diagram-panel--block svg,
              .diagram-panel--mindmap svg,
              .diagram-panel--class svg,
              .diagram-panel--er svg,
              .diagram-panel--journey svg,
              .diagram-panel--requirement svg,
              .diagram-panel--kanban svg,
              .diagram-panel--architecture svg {
                max-width: min(520px, 100%);
              }

              .grid.is-focus .effect-card.is-selected .effect-media {
                min-height: 80vh;
                height: 80vh;
              }
            }
          </style>
        </head>
        <body>
          <main>
            <header>
              <div>
                <h1>Diagram Effects</h1>
                <p>Seven rendering treatments applied to every diagram family currently rendered by atelier/diagram, with a different font strategy per style.</p>
              </div>
              <div class="meta">atelier/diagram</div>
            </header>
            <section class="grid">
              {{ cards }}
            </section>
            <nav class="gallery-controls" aria-label="Gallery navigation">
              <button type="button" data-gallery-prev aria-label="Previous style">←</button>
              <button type="button" data-gallery-next aria-label="Next style">→</button>
              <button type="button" data-gallery-next aria-label="Next style">↑</button>
              <button type="button" data-gallery-prev aria-label="Previous style">↓</button>
            </nav>
            <nav class="viewport-controls" aria-label="Viewport controls">
              <button type="button" data-viewport-zoom-in aria-label="Zoom in">+</button>
              <button type="button" data-viewport-zoom-out aria-label="Zoom out">-</button>
              <button type="button" data-viewport-reset aria-label="Reset viewport">0</button>
              <button type="button" data-viewport-fullscreen aria-label="Fullscreen">⛶</button>
            </nav>
          </main>
          <script>
            (() => {
              const grid = document.querySelector('.grid');
              const cards = [...document.querySelectorAll('[data-gallery-card]')];
              const nextButtons = [...document.querySelectorAll('[data-gallery-next]')];
              const prevButtons = [...document.querySelectorAll('[data-gallery-prev]')];
              const zoomInButton = document.querySelector('[data-viewport-zoom-in]');
              const zoomOutButton = document.querySelector('[data-viewport-zoom-out]');
              const resetButton = document.querySelector('[data-viewport-reset]');
              const fullscreenButton = document.querySelector('[data-viewport-fullscreen]');
              const viewports = new WeakMap();
              let selectedIndex = -1;
              let dragState = null;

              const viewportFor = (card) => {
                if (!viewports.has(card)) {
                  viewports.set(card, { scale: 1, x: 0, y: 0 });
                }

                return viewports.get(card);
              };

              const selectedCard = () => selectedIndex < 0 ? null : cards[selectedIndex];

              const applyViewport = (card) => {
                const sheet = card?.querySelector('.diagram-pair');
                if (!sheet) {
                  return;
                }
                const viewport = viewportFor(card);
                sheet.style.transform = `translate(${viewport.x}px, ${viewport.y}px) scale(${viewport.scale})`;
              };

              const adjustZoom = (delta, originX = 0, originY = 0) => {
                const card = selectedCard();
                if (!card) {
                  return;
                }
                const viewport = viewportFor(card);
                const previousScale = viewport.scale;
                viewport.scale = Math.min(4, Math.max(0.45, viewport.scale * delta));
                if (originX || originY) {
                  const ratio = viewport.scale / previousScale;
                  viewport.x = originX - (originX - viewport.x) * ratio;
                  viewport.y = originY - (originY - viewport.y) * ratio;
                }
                applyViewport(card);
              };

              const resetViewport = (card = selectedCard()) => {
                if (!card) {
                  return;
                }
                const viewport = viewportFor(card);
                viewport.scale = 1;
                viewport.x = 0;
                viewport.y = 0;
                applyViewport(card);
              };

              const select = (index) => {
                if (!cards.length) {
                  return;
                }
                selectedIndex = (index + cards.length) % cards.length;
                document.body.classList.add('gallery-open');
                grid?.classList.add('is-focus');
                cards.forEach((card, cardIndex) => {
                  const selected = cardIndex === selectedIndex;
                  card.classList.toggle('is-selected', selected);
                  card.setAttribute('aria-pressed', selected ? 'true' : 'false');
                  if (selected) {
                    applyViewport(card);
                  }
                });
              };

              const close = () => {
                selectedIndex = -1;
                document.body.classList.remove('gallery-open');
                grid?.classList.remove('is-focus');
                cards.forEach((card) => {
                  card.classList.remove('is-selected');
                  card.setAttribute('aria-pressed', 'false');
                });
              };

              cards.forEach((card, index) => {
                const media = card.querySelector('.effect-media');

                card.addEventListener('click', () => {
                  if (selectedIndex !== index) {
                    select(index);
                  }
                });
                card.addEventListener('keydown', (event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    select(index);
                  }
                });

                media?.addEventListener('wheel', (event) => {
                  if (selectedIndex !== index) {
                    return;
                  }
                  event.preventDefault();
                  const rect = media.getBoundingClientRect();
                  adjustZoom(event.deltaY < 0 ? 1.08 : 0.92, event.clientX - rect.left - rect.width / 2, event.clientY - rect.top - rect.height / 2);
                }, { passive: false });

                media?.addEventListener('pointerdown', (event) => {
                  if (selectedIndex !== index || event.button !== 0) {
                    return;
                  }
                  const viewport = viewportFor(card);
                  dragState = {
                    card,
                    media,
                    pointerId: event.pointerId,
                    startX: event.clientX,
                    startY: event.clientY,
                    x: viewport.x,
                    y: viewport.y,
                  };
                  media.classList.add('is-panning');
                  media.setPointerCapture(event.pointerId);
                });

                media?.addEventListener('pointermove', (event) => {
                  if (!dragState || dragState.card !== card || dragState.pointerId !== event.pointerId) {
                    return;
                  }
                  const viewport = viewportFor(card);
                  viewport.x = dragState.x + event.clientX - dragState.startX;
                  viewport.y = dragState.y + event.clientY - dragState.startY;
                  applyViewport(card);
                });

                media?.addEventListener('pointerup', (event) => {
                  if (dragState?.pointerId === event.pointerId) {
                    dragState.media?.classList.remove('is-panning');
                    dragState = null;
                  }
                });

                media?.addEventListener('pointercancel', () => {
                  dragState?.media?.classList.remove('is-panning');
                  dragState = null;
                });
              });

              nextButtons.forEach((button) => {
                button.addEventListener('click', () => select(selectedIndex < 0 ? 0 : selectedIndex + 1));
              });
              prevButtons.forEach((button) => {
                button.addEventListener('click', () => select(selectedIndex < 0 ? cards.length - 1 : selectedIndex - 1));
              });
              zoomInButton?.addEventListener('click', () => adjustZoom(1.18));
              zoomOutButton?.addEventListener('click', () => adjustZoom(0.84));
              resetButton?.addEventListener('click', () => resetViewport());
              fullscreenButton?.addEventListener('click', async () => {
                const card = selectedCard();
                if (!card) {
                  return;
                }
                if (document.fullscreenElement) {
                  await document.exitFullscreen();

                  return;
                }
                await card.requestFullscreen();
              });

              document.addEventListener('keydown', (event) => {
                if (selectedIndex < 0) {
                  return;
                }
                if (event.key === 'Escape') {
                  close();
                }
                if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
                  event.preventDefault();
                  select(selectedIndex + 1);
                }
                if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
                  event.preventDefault();
                  select(selectedIndex - 1);
                }
                if (event.key === '+' || event.key === '=') {
                  event.preventDefault();
                  adjustZoom(1.18);
                }
                if (event.key === '-' || event.key === '_') {
                  event.preventDefault();
                  adjustZoom(0.84);
                }
                if (event.key === '0') {
                  event.preventDefault();
                  resetViewport();
                }
              });
            })();
          </script>
        </body>
        </html>
        HTML,
        ['{{ cards }}' => implode("\n", $cards)],
    );
}

function effectsDemoNumber(float $value): string
{
    return rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
}

function effectsDemoScopedId(string $scope, string $id): string
{
    return $scope.'-'.$id;
}

function effectsDemoEscape(string $value): string
{
    return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
}
