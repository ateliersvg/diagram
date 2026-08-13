<?php

declare(strict_types=1);

/*
 * Generates a full-viewport HTML showcase deck for the Mermaid-like
 * diagram types supported by the package.
 *
 * Each section embeds the rendered SVG, the canonical Mermaid source, and a
 * small PHP builder sample. The page is navigable with left/right arrow keys
 * and is exported to examples/output/showcase/index.html.
 *
 * Diagrams are rendered with the dark theme so the SVGs sit on the deck's
 * dark surface instead of carrying an opaque light background.
 */

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/state-machine.php';
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

$sections = [
    [
        'slug' => 'state',
        'kicker' => 'State',
        'title' => 'Order lifecycle',
        'description' => 'State nodes, labeled transitions, direction changes, and initial/final markers.',
        'svg' => __DIR__.'/output/showcase/state.svg',
        'markdown' => __DIR__.'/output/state.md',
        'options' => [
            'Direction can switch between top-to-bottom and left-to-right.',
            'Transitions keep labels on the edges, not in a separate legend.',
            'Initial and final states remain explicit nodes in the model.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                Diagram::state()
                    ->direction(Direction::TopToBottom)
                    ->state('Review', 'In review')
                    ->initial('Draft')
                    ->transition('Draft', 'Review', 'submit')
                    ->transition('Review', 'Approved', 'approve')
                    ->final('Delivered')
                    ->build(),
            )->saveSvg('state.svg');
            PHP,
    ],
    [
        'slug' => 'git',
        'kicker' => 'Git graph',
        'title' => 'Release history',
        'description' => 'Branches, commits, checkout flow, merge commits, and release tags.',
        'svg' => __DIR__.'/output/showcase/git.svg',
        'markdown' => __DIR__.'/output/git.md',
        'options' => [
            'Branch lanes stay readable while commits are interleaved.',
            'Checkout and merge are first-class semantic operations.',
            'Tag and commit metadata are part of the canonical source.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                (new GitGraphBuilder())
                    ->commit('a1b2c3d')
                    ->branch('feature-auth')
                    ->commit(tag: 'auth-beta')
                    ->checkout('main')
                    ->merge('feature-auth')
                    ->commit('f9e8d7c', 'v1.0')
                    ->build(),
            )->saveSvg('git.svg');
            PHP,
    ],
    [
        'slug' => 'sequence',
        'kicker' => 'Sequence',
        'title' => 'Checkout sequence',
        'description' => 'Participants, messages, activations, branch blocks, and self-messages.',
        'svg' => __DIR__.'/output/showcase/sequence.svg',
        'markdown' => __DIR__.'/output/sequence.md',
        'options' => [
            'Activations create compact lifeline gutters.',
            'Alt, opt, loop, and par blocks share the same branch rules.',
            'Self-messages and dashed replies stay stable in round-trips.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                (new SequenceDiagramBuilder())
                    ->title('Checkout sequence')
                    ->participant('User', 'Customer')
                    ->participant('Api', 'API')
                    ->message('User', 'Api', 'Confirm cart')
                    ->activate('Api')
                    ->message('Api', 'User', 'Order accepted', MessageArrow::Dashed)
                    ->deactivate('Api')
                    ->build(),
            )->saveSvg('sequence.svg');
            PHP,
    ],
    [
        'slug' => 'flowchart',
        'kicker' => 'Flowchart',
        'title' => 'Checkout flow',
        'description' => 'Ranked nodes, routed edges, nested subgraphs, and edge labels with collision avoidance.',
        'svg' => __DIR__.'/output/showcase/flowchart.svg',
        'markdown' => __DIR__.'/output/flowchart.md',
        'options' => [
            'Nodes are ranked by source order and edge pressure.',
            'Subgraphs become clusters around solved node frames.',
            'Edge labels now avoid occupied boxes through layout helpers.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildFlowchartWorkflow(),
            )->saveSvg('flowchart.svg');
            PHP,
    ],
    [
        'slug' => 'class',
        'kicker' => 'Class',
        'title' => 'Order model',
        'description' => 'Class boxes, member rows, and routed relations with labels.',
        'svg' => __DIR__.'/output/showcase/class.svg',
        'markdown' => __DIR__.'/output/class.md',
        'options' => [
            'Members are laid out as stable row grids inside each class box.',
            'Relationship labels stay attached to the routed edge.',
            'Canonical Mermaid keeps the supported subset small and exact.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildClassDiagram(),
            )->saveSvg('class.svg');
            PHP,
    ],
    [
        'slug' => 'timeline',
        'kicker' => 'Timeline',
        'title' => 'Product launch',
        'description' => 'Sections, ordered events, and a horizontal timeline without chart axes.',
        'svg' => __DIR__.'/output/showcase/timeline.svg',
        'markdown' => __DIR__.'/output/timeline.md',
        'options' => [
            'Sections stack vertically while the narrative stays source-ordered.',
            'Events are labels on the timeline, not quantitative bars.',
            'The layout stays deterministic without a full scale engine.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildTimelineDiagram(),
            )->saveSvg('timeline.svg');
            PHP,
    ],
    [
        'slug' => 'er',
        'kicker' => 'ER',
        'title' => 'Commerce schema',
        'description' => 'Entities, typed attributes, and cardinality-rich relationships.',
        'svg' => __DIR__.'/output/showcase/er.svg',
        'markdown' => __DIR__.'/output/er.md',
        'options' => [
            'Entity blocks stay readable as compact tables.',
            'Cardinality markers are validated as part of the model.',
            'Relationship labels ride the routed edge like other diagrams.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildErDiagram(),
            )->saveSvg('er.svg');
            PHP,
    ],
    [
        'slug' => 'journey',
        'kicker' => 'Journey',
        'title' => 'Checkout experience',
        'description' => 'Phases, task rows, actor captions, and score badges.',
        'svg' => __DIR__.'/output/showcase/journey.svg',
        'markdown' => __DIR__.'/output/journey.md',
        'options' => [
            'Sections group the journey into phases.',
            'Task scores remain visual intensity, not a chart axis.',
            'Actors are compact captions rather than timeline lanes.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildJourneyDiagram(),
            )->saveSvg('journey.svg');
            PHP,
    ],
    [
        'slug' => 'mindmap',
        'kicker' => 'Mindmap',
        'title' => 'Atelier surface',
        'description' => 'A strict rooted tree with deterministic indentation and spacing.',
        'svg' => __DIR__.'/output/showcase/mindmap.svg',
        'markdown' => __DIR__.'/output/mindmap.md',
        'options' => [
            'One root only, with strict indentation for v0.',
            'No force-directed placement, no hidden heuristics.',
            'The tree shape is the meaning, not a decorative layout.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildMindmap(),
            )->saveSvg('mindmap.svg');
            PHP,
    ],
    [
        'slug' => 'requirement',
        'kicker' => 'Requirement',
        'title' => 'Checkout requirements',
        'description' => 'Requirement and element nodes with typed relationships between them.',
        'svg' => __DIR__.'/output/showcase/requirement.svg',
        'markdown' => __DIR__.'/output/requirement.md',
        'options' => [
            'Requirements and elements stay separate node kinds.',
            'Relationship kinds remain explicit and validated.',
            'The layout reuses routing and box primitives rather than inventing new ones.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildRequirementDiagram(),
            )->saveSvg('requirement.svg');
            PHP,
    ],
    [
        'slug' => 'kanban',
        'kicker' => 'Kanban',
        'title' => 'Delivery board',
        'description' => 'Columns, cards, and a board layout that stays ordered and compact.',
        'svg' => __DIR__.'/output/showcase/kanban.svg',
        'markdown' => __DIR__.'/output/kanban.md',
        'options' => [
            'Columns define the flow, cards define the work.',
            'Ordering stays source-driven and predictable.',
            'The parser enforces the board shape and empty-column guards.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildKanbanDiagram(),
            )->saveSvg('kanban.svg');
            PHP,
    ],
    [
        'slug' => 'block',
        'kicker' => 'Block',
        'title' => 'Layout kernel',
        'description' => 'Blocks, groups, and relationships for documenting infrastructure or kernels.',
        'svg' => __DIR__.'/output/showcase/block.svg',
        'markdown' => __DIR__.'/output/block.md',
        'options' => [
            'Groups create nested frames around related blocks.',
            'Relationships are still routed geometrically through layout.',
            'The subset is intentionally smaller than a generic graph language.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildBlockDiagram(),
            )->saveSvg('block.svg');
            PHP,
    ],
    [
        'slug' => 'architecture',
        'kicker' => 'Architecture',
        'title' => 'Checkout platform',
        'description' => 'Groups, node kinds, and cross-tier relationships for system views.',
        'svg' => __DIR__.'/output/showcase/architecture.svg',
        'markdown' => __DIR__.'/output/architecture.md',
        'options' => [
            'Node kinds stay explicit: people, systems, containers, queues, databases.',
            'Groups form the coarse layout tiers.',
            'Relationship labels use the same routed-edge handling as other graph diagrams.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildArchitectureDiagram(),
            )->saveSvg('architecture.svg');
            PHP,
    ],
    [
        'slug' => 'c4',
        'kicker' => 'C4',
        'title' => 'Shop platform',
        'description' => 'C4-style people, systems, containers, boundaries, technologies, and routed relationships.',
        'svg' => __DIR__.'/output/showcase/c4.svg',
        'markdown' => __DIR__.'/output/c4.md',
        'options' => [
            'Context, container, and component views share one strict model.',
            'Boundaries create C4-style system frames around contained elements.',
            'Relationship labels can include both intent and technology.',
        ],
        'php' => <<<'PHP'
            Diagram::of(
                buildC4Diagram(),
            )->saveSvg('c4.svg');
            PHP,
    ],
];

$outputDir = __DIR__.'/output/showcase';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

// Render the deck's diagrams with the dark theme into this directory, so each
// slide embeds a dark-surface SVG that matches the page chrome. Keyed by slug
// to match each section's 'svg' path below.
$darkTheme = Theme::dark();
$darkDiagrams = [
    'state' => Diagram::of(buildStateMachineDiagram(Direction::TopToBottom, 'Order lifecycle')),
    'git' => Diagram::of(buildGitHistory('Release history')),
    'sequence' => Diagram::of(buildSequenceWorkflow()),
    'flowchart' => Diagram::of(buildFlowchartWorkflow()),
    'class' => Diagram::of(buildClassDiagram()),
    'timeline' => Diagram::of(buildTimelineDiagram()),
    'er' => Diagram::of(buildErDiagram()),
    'journey' => Diagram::of(buildJourneyDiagram()),
    'mindmap' => Diagram::of(buildMindmap()),
    'requirement' => Diagram::of(buildRequirementDiagram()),
    'kanban' => Diagram::of(buildKanbanDiagram()),
    'block' => Diagram::of(buildBlockDiagram()),
    'architecture' => Diagram::of(buildArchitectureDiagram()),
    'c4' => Diagram::of(buildC4Diagram()),
];
foreach ($darkDiagrams as $slug => $diagram) {
    $diagram->saveSvg($outputDir.'/'.$slug.'.svg', $darkTheme);
}

$html = renderShowcasePage($sections);
file_put_contents($outputDir.'/index.html', $html);

echo 'Wrote '.$outputDir.'/index.html'.PHP_EOL;

/**
 * @param list<array{slug: string, kicker: string, title: string, description: string, svg: string, markdown: string, options: list<string>, php: string}> $sections
 */
function renderShowcasePage(array $sections): string
{
    $slides = [];
    foreach ($sections as $index => $section) {
        $slides[] = renderSlide($section, $index + 1, \count($sections));
    }

    $buttons = [];
    foreach ($sections as $index => $section) {
        $buttons[] = sprintf(
            '<button class="dot" type="button" data-slide="%d" aria-label="Go to %s"></button>',
            $index,
            escapeHtml($section['title']),
        );
    }

    $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Atelier Diagram Showcase</title>
  <style>
    :root {
      color-scheme: dark;
      --bg: #0b1220;
      --surface: #121b2e;
      --surface-2: #0f172a;
      --border: #26324a;
      --text: #e5eefc;
      --muted: #9eb0cc;
      --accent: #67e8f9;
      --accent-2: #93c5fd;
      --code: #07101f;
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      background: var(--bg);
      color: var(--text);
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    body {
      display: flex;
      flex-direction: column;
    }

    .chrome {
      position: fixed;
      inset: 0 0 auto 0;
      z-index: 20;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 14px 18px;
      pointer-events: none;
    }

    .brand {
      pointer-events: auto;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      border: 1px solid rgba(103, 232, 249, 0.18);
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.82);
      backdrop-filter: blur(12px);
      color: var(--text);
      font-size: 13px;
      letter-spacing: 0;
    }

    .brand strong {
      color: var(--accent);
      font-weight: 700;
    }

    .pager {
      pointer-events: auto;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      border: 1px solid rgba(103, 232, 249, 0.18);
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.82);
      backdrop-filter: blur(12px);
    }

    .pager button {
      appearance: none;
      border: 0;
      background: transparent;
      color: var(--text);
      font: inherit;
      cursor: pointer;
      padding: 0 4px;
    }

    .pager button:hover,
    .pager button:focus-visible {
      color: var(--accent);
      outline: none;
    }

    .count {
      min-width: 4.5em;
      text-align: center;
      color: var(--muted);
      font-size: 12px;
    }

    .deck {
      display: flex;
      width: 100vw;
      height: 100vh;
      overflow-x: auto;
      overflow-y: hidden;
      scroll-snap-type: x mandatory;
      scroll-behavior: smooth;
      scrollbar-width: none;
    }

    .deck::-webkit-scrollbar { display: none; }

    .slide {
      flex: 0 0 100vw;
      height: 100vh;
      scroll-snap-align: start;
      padding: 72px 20px 20px;
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(340px, 0.85fr);
      gap: 20px;
      min-width: 0;
    }

    .hero,
    .details {
      min-width: 0;
      min-height: 0;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .hero {
      padding: 0 0 0 8px;
    }

    .kicker {
      color: var(--accent);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.12em;
    }

    h1 {
      margin: 0;
      font-size: clamp(28px, 3vw, 42px);
      line-height: 1.02;
      letter-spacing: 0;
    }

    .description {
      max-width: 66ch;
      margin: 0;
      color: var(--muted);
      line-height: 1.5;
      font-size: 15px;
    }

    .demo {
      flex: 1 1 auto;
      min-height: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 18px;
      border: 1px solid rgba(103, 232, 249, 0.18);
      border-radius: 18px;
      /* Match Theme::dark() background so a diagram's own canvas fill
         blends into the card instead of showing as a nested rectangle. */
      background: #020617;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
      overflow: hidden;
    }

    .demo svg {
      max-width: 100%;
      max-height: 100%;
      height: auto;
      display: block;
    }

    .panel {
      min-height: 0;
      padding: 14px 16px;
      border: 1px solid rgba(103, 232, 249, 0.12);
      border-radius: 16px;
      background: rgba(15, 23, 42, 0.88);
      overflow: auto;
    }

    .panel h2 {
      margin: 0 0 10px;
      font-size: 14px;
      color: var(--accent-2);
    }

    .panel ul {
      margin: 0;
      padding-left: 1.1em;
      color: var(--text);
      line-height: 1.5;
    }

    .panel li + li {
      margin-top: 8px;
    }

    .code {
      margin: 0;
      padding: 14px 16px;
      border-radius: 14px;
      background: var(--code);
      border: 1px solid rgba(103, 232, 249, 0.10);
      color: #dbeafe;
      overflow: auto;
      white-space: pre;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
      font-size: 12px;
      line-height: 1.45;
    }

    .stack {
      min-height: 0;
      display: grid;
      grid-template-rows: auto auto auto;
      gap: 12px;
    }

    .footer-note {
      position: fixed;
      left: 18px;
      bottom: 14px;
      z-index: 20;
      color: rgba(158, 176, 204, 0.75);
      font-size: 12px;
      pointer-events: none;
    }

    .dotrow {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      max-width: 40vw;
      justify-content: flex-end;
    }

    .dot {
      width: 10px;
      height: 10px;
      padding: 0;
      border-radius: 999px;
      border: 1px solid rgba(147, 197, 253, 0.6);
      background: transparent;
      opacity: 0.45;
    }

    .dot[aria-current="true"] {
      background: var(--accent);
      border-color: var(--accent);
      opacity: 1;
    }

    @media (max-width: 980px) {
      .slide {
        grid-template-columns: 1fr;
        grid-template-rows: minmax(0, 1fr) minmax(0, auto);
      }

      .hero {
        padding: 0;
      }

      .dotrow {
        max-width: 60vw;
      }
    }
  </style>
</head>
<body>
  <header class="chrome">
    <div class="brand"><strong>Atelier</strong> Diagram Showcase</div>
    <div class="pager">
      <button type="button" id="prev" aria-label="Previous section">←</button>
      <div class="count"><span id="current">01</span>/<span id="total">__TOTAL__</span></div>
      <button type="button" id="next" aria-label="Next section">→</button>
      <div class="dotrow">__BUTTONS__</div>
    </div>
  </header>

  <main class="deck" id="deck">
    __SLIDES__
  </main>

  <div class="footer-note">Left / right navigation is wired in the page.</div>

  <script>
    (() => {
      const deck = document.getElementById('deck');
      const slides = Array.from(document.querySelectorAll('.slide'));
      const dots = Array.from(document.querySelectorAll('[data-slide]'));
      const current = document.getElementById('current');
      const total = slides.length;
      let index = 0;

      const pad = (value) => String(value).padStart(2, '0');

      function render() {
        current.textContent = pad(index + 1);
        dots.forEach((dot) => {
          dot.setAttribute('aria-current', Number(dot.dataset.slide) === index ? 'true' : 'false');
        });
      }

      function clamp(value) {
        return Math.max(0, Math.min(total - 1, value));
      }

      function go(next) {
        index = clamp(next);
        render();
        deck.scrollTo({ left: slides[index].offsetLeft, behavior: 'smooth' });
      }

      function nearestIndex() {
        const left = deck.scrollLeft;
        let best = 0;
        let bestDistance = Number.POSITIVE_INFINITY;
        slides.forEach((slide, slideIndex) => {
          const distance = Math.abs(slide.offsetLeft - left);
          if (distance < bestDistance) {
            bestDistance = distance;
            best = slideIndex;
          }
        });
        return best;
      }

      let ticking = false;
      deck.addEventListener('scroll', () => {
        if (ticking) {
          return;
        }

        ticking = true;
        requestAnimationFrame(() => {
          index = nearestIndex();
          render();
          ticking = false;
        });
      });

      document.getElementById('prev').addEventListener('click', () => go(index - 1));
      document.getElementById('next').addEventListener('click', () => go(index + 1));
      dots.forEach((dot) => dot.addEventListener('click', () => go(Number(dot.dataset.slide))));

      document.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          go(index - 1);
        }

        if (event.key === 'ArrowRight') {
          event.preventDefault();
          go(index + 1);
        }
      });

      render();
    })();
  </script>
</body>
</html>
HTML;

    return strtr($html, [
        '__TOTAL__' => (string) \count($sections),
        '__BUTTONS__' => implode('', $buttons),
        '__SLIDES__' => implode('', $slides),
    ]);
}

/**
 * @param array{slug: string, kicker: string, title: string, description: string, svg: string, markdown: string, options: list<string>, php: string} $section
 */
function renderSlide(array $section, int $position, int $total): string
{
    $svg = file_get_contents($section['svg']);
    if (false === $svg) {
        throw new RuntimeException(sprintf('Cannot read SVG asset: %s', $section['svg']));
    }
    $svg = preg_replace('/^<\?xml[^>]+>\s*/', '', $svg) ?? $svg;

    $markdown = file_get_contents($section['markdown']);
    if (false === $markdown) {
        throw new RuntimeException(sprintf('Cannot read markdown asset: %s', $section['markdown']));
    }

    $options = [];
    foreach ($section['options'] as $option) {
        $options[] = '<li>'.escapeHtml($option).'</li>';
    }

    return sprintf(
        '<section class="slide" id="%s" aria-label="%s %02d of %02d"><div class="hero"><div class="kicker">%s</div><h1>%s</h1><p class="description">%s</p><div class="demo">%s</div></div><aside class="details"><section class="panel"><h2>Options</h2><ul>%s</ul></section><section class="panel"><h2>Markdown</h2><pre class="code"><code class="language-markdown">%s</code></pre></section><section class="panel"><h2>PHP</h2><pre class="code"><code class="language-php">%s</code></pre></section></aside></section>',
        escapeHtml($section['slug']),
        escapeHtml($section['title']),
        $position,
        $total,
        escapeHtml($section['kicker']),
        escapeHtml($section['title']),
        escapeHtml($section['description']),
        $svg,
        implode('', $options),
        escapeHtml($markdown),
        escapeHtml($section['php']),
    );
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
}
