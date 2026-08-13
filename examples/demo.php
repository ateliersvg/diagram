<?php

declare(strict_types=1);

/*
 * Generates the local demo surface for atelier/diagram.
 *
 * Run:
 *   php examples/demo.php
 *   php examples/demo.php --open
 *   php examples/demo.php --only=gallery
 */

require dirname(__DIR__).'/vendor/autoload.php';

$argv = [];
$serverArgv = $_SERVER['argv'] ?? [];
if (is_array($serverArgv)) {
    foreach ($serverArgv as $arg) {
        if (is_string($arg)) {
            $argv[] = $arg;
        }
    }
}
$options = parseDemoOptions($argv);
$outputDir = __DIR__.'/output';

if ($options['clean']) {
    removeDirectory($outputDir);
}
ensureDirectory($outputDir);

$runs = [];
foreach (demoTasks() as $task) {
    if (!in_array($task['slug'], $options['only'], true)) {
        $runs[$task['slug']] = DemoRun::skipped($task);
        continue;
    }

    $runs[$task['slug']] = runDemoTask($task);
}

writeGalleryIndex($outputDir.'/gallery/index.html', demoFamilies());
writeDemoIndex($outputDir.'/index.html', demoTasks(), $runs, demoFamilies());

echo 'Wrote '.$outputDir.'/gallery/index.html'.\PHP_EOL;
echo 'Wrote '.$outputDir.'/index.html'.\PHP_EOL;

if (in_array('gallery', $options['only'], true)) {
    assertGalleryCoverage($outputDir, demoFamilies());
}

if ($options['open']) {
    openInBrowser($outputDir.'/index.html');
}

/**
 * @param list<string> $argv
 *
 * @return array{only: list<string>, open: bool, clean: bool}
 */
function parseDemoOptions(array $argv): array
{
    $known = array_column(demoTasks(), 'slug');
    $only = $known;
    $open = false;
    $clean = false;

    foreach (array_slice($argv, 1) as $arg) {
        if ('--open' === $arg) {
            $open = true;
            continue;
        }
        if ('--clean' === $arg) {
            $clean = true;
            continue;
        }
        if (str_starts_with($arg, '--only=')) {
            $requested = array_values(array_filter(array_map('trim', explode(',', substr($arg, 7)))));
            $unknown = array_diff($requested, $known);
            if ([] !== $unknown) {
                throw new RuntimeException(sprintf('Unknown demo section: %s', implode(', ', $unknown)));
            }
            $only = $requested;
            continue;
        }

        throw new RuntimeException(sprintf('Unknown option: %s', $arg));
    }

    return ['only' => $only, 'open' => $open, 'clean' => $clean];
}

/**
 * @return list<array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool}>
 */
function demoTasks(): array
{
    return [
        [
            'slug' => 'gallery',
            'title' => 'Gallery',
            'command' => 'php examples/gallery.php',
            'script' => __DIR__.'/gallery.php',
            'output' => 'examples/output/gallery/index.html',
            'description' => 'Reference SVG and Mermaid artifacts for every diagram family.',
        ],
        [
            'slug' => 'showcase',
            'title' => 'Showcase',
            'command' => 'php examples/showcase-page.php',
            'script' => __DIR__.'/showcase-page.php',
            'output' => 'examples/output/showcase/index.html',
            'description' => 'Full-viewport visual deck for the main diagram families.',
        ],
        [
            'slug' => 'label-polish',
            'title' => 'Label Polish',
            'command' => 'php examples/label-polish.php',
            'script' => __DIR__.'/label-polish.php',
            'output' => 'examples/output/label-polish/index.html',
            'description' => 'Focused pages for route labels, legends, values, wrapping, and z-order.',
        ],
        [
            'slug' => 'effects-demo',
            'title' => 'Effects Demo',
            'command' => 'php examples/effects-demo.php',
            'script' => __DIR__.'/effects-demo.php',
            'output' => 'examples/output/effects-demo/index.html',
            'description' => 'Theme and renderer experiments across several visual treatments.',
            'optional' => true,
        ],
    ];
}

/**
 * @return list<array{slug: string, title: string, preview: string, builder: string|null, parsed: string|null, markdown: string|null, textual: bool}>
 */
function demoFamilies(): array
{
    return [
        ['slug' => 'state', 'title' => 'State', 'preview' => 'state-tb.svg', 'builder' => 'state-tb.svg', 'parsed' => 'state-parsed.svg', 'markdown' => 'state.md', 'textual' => true],
        ['slug' => 'venn', 'title' => 'Venn', 'preview' => 'venn-3.svg', 'builder' => 'venn-3.svg', 'parsed' => null, 'markdown' => null, 'textual' => false],
        ['slug' => 'git', 'title' => 'Git graph', 'preview' => 'git-builder.svg', 'builder' => 'git-builder.svg', 'parsed' => 'git-parsed.svg', 'markdown' => 'git.md', 'textual' => true],
        ['slug' => 'sequence', 'title' => 'Sequence', 'preview' => 'sequence-builder.svg', 'builder' => 'sequence-builder.svg', 'parsed' => 'sequence-parsed.svg', 'markdown' => 'sequence.md', 'textual' => true],
        ['slug' => 'flowchart', 'title' => 'Flowchart', 'preview' => 'flowchart-builder.svg', 'builder' => 'flowchart-builder.svg', 'parsed' => 'flowchart-parsed.svg', 'markdown' => 'flowchart.md', 'textual' => true],
        ['slug' => 'class', 'title' => 'Class', 'preview' => 'class-builder.svg', 'builder' => 'class-builder.svg', 'parsed' => 'class-parsed.svg', 'markdown' => 'class.md', 'textual' => true],
        ['slug' => 'er', 'title' => 'ER', 'preview' => 'er-builder.svg', 'builder' => 'er-builder.svg', 'parsed' => 'er-parsed.svg', 'markdown' => 'er.md', 'textual' => true],
        ['slug' => 'timeline', 'title' => 'Timeline', 'preview' => 'timeline-builder.svg', 'builder' => 'timeline-builder.svg', 'parsed' => 'timeline-parsed.svg', 'markdown' => 'timeline.md', 'textual' => true],
        ['slug' => 'journey', 'title' => 'Journey', 'preview' => 'journey-builder.svg', 'builder' => 'journey-builder.svg', 'parsed' => 'journey-parsed.svg', 'markdown' => 'journey.md', 'textual' => true],
        ['slug' => 'mindmap', 'title' => 'Mindmap', 'preview' => 'mindmap-builder.svg', 'builder' => 'mindmap-builder.svg', 'parsed' => 'mindmap-parsed.svg', 'markdown' => 'mindmap.md', 'textual' => true],
        ['slug' => 'requirement', 'title' => 'Requirement', 'preview' => 'requirement-builder.svg', 'builder' => 'requirement-builder.svg', 'parsed' => 'requirement-parsed.svg', 'markdown' => 'requirement.md', 'textual' => true],
        ['slug' => 'kanban', 'title' => 'Kanban', 'preview' => 'kanban-builder.svg', 'builder' => 'kanban-builder.svg', 'parsed' => 'kanban-parsed.svg', 'markdown' => 'kanban.md', 'textual' => true],
        ['slug' => 'block', 'title' => 'Block', 'preview' => 'block-builder.svg', 'builder' => 'block-builder.svg', 'parsed' => 'block-parsed.svg', 'markdown' => 'block.md', 'textual' => true],
        ['slug' => 'architecture', 'title' => 'Architecture', 'preview' => 'architecture-builder.svg', 'builder' => 'architecture-builder.svg', 'parsed' => 'architecture-parsed.svg', 'markdown' => 'architecture.md', 'textual' => true],
        ['slug' => 'c4', 'title' => 'C4', 'preview' => 'c4-builder.svg', 'builder' => 'c4-builder.svg', 'parsed' => 'c4-parsed.svg', 'markdown' => 'c4.md', 'textual' => true],
    ];
}

/**
 * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
 */
function runDemoTask(array $task): DemoRun
{
    if (null === $task['script'] || !is_file($task['script'])) {
        return DemoRun::missing($task);
    }

    echo 'Running '.$task['command'].\PHP_EOL;
    $process = proc_open(
        [\PHP_BINARY, $task['script']],
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__),
    );

    if (!is_resource($process)) {
        return DemoRun::failed($task, 'Could not start process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $output = trim(trim((string) $stdout)."\n".trim((string) $stderr));
    if ('' !== $output) {
        echo $output.\PHP_EOL;
    }

    return 0 === $exitCode ? DemoRun::generated($task, $output) : DemoRun::failed($task, $output);
}

/**
 * @param list<array{slug: string, title: string, preview: string, builder: string|null, parsed: string|null, markdown: string|null, textual: bool}> $families
 */
function writeGalleryIndex(string $path, array $families): void
{
    ensureDirectory(dirname($path));

    $cards = [];
    foreach ($families as $family) {
        $preview = '../'.$family['preview'];
        $previewPath = dirname($path).'/'.$preview;
        $styleStatus = svgHasVisibleStyles($previewPath) ? 'Styled SVG' : 'Style check missing';
        $links = [];
        foreach (['builder' => 'Builder SVG', 'parsed' => 'Parsed SVG', 'markdown' => 'Mermaid'] as $key => $label) {
            $file = $family[$key];
            if (null === $file) {
                $links[] = '<span class="muted">'.$label.': not applicable</span>';
                continue;
            }
            $href = '../'.$file;
            $exists = is_file(dirname($path).'/'.$href);
            $links[] = $exists
                ? '<a href="'.escapeHtml($href).'">'.$label.'</a>'
                : '<span class="missing">'.$label.': missing</span>';
        }

        $cards[] = strtr(
            <<<'HTML'
            <article class="diagram-card">
              <header>
                <h2>{{ title }}</h2>
                <span>{{ styleStatus }}</span>
              </header>
              <a class="preview" href="{{ preview }}"><img src="{{ preview }}" alt="{{ title }} preview"></a>
              <nav>{{ links }}</nav>
            </article>
            HTML,
            [
                '{{ title }}' => escapeHtml($family['title']),
                '{{ styleStatus }}' => escapeHtml($styleStatus),
                '{{ preview }}' => escapeHtml($preview),
                '{{ links }}' => implode("\n", $links),
            ],
        );
    }

    writeFile($path, demoShellHtml(
        'Gallery',
        'All diagram families with builder output, parsed output where supported, and canonical Mermaid where available.',
        implode("\n", $cards),
        '../index.html',
    ));
}

/**
 * @param list<array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool}>       $tasks
 * @param array<string, DemoRun>                                                                                                                     $runs
 * @param list<array{slug: string, title: string, preview: string, builder: string|null, parsed: string|null, markdown: string|null, textual: bool}> $families
 */
function writeDemoIndex(string $path, array $tasks, array $runs, array $families): void
{
    $cards = [];
    foreach ($tasks as $task) {
        $href = outputHref($task['output']);
        $status = $runs[$task['slug']] ?? DemoRun::skipped($task);
        if (!is_file(dirname(__DIR__).'/'.$task['output']) && 'skipped' === $status->status) {
            $status = DemoRun::missingOutput($task);
        }

        $cards[] = strtr(
            <<<'HTML'
            <article class="index-card">
              <div>
                <strong>{{ title }}</strong>
                <span class="status {{ statusClass }}">{{ status }}</span>
              </div>
              <p>{{ description }}</p>
              <code>{{ command }}</code>
              <a href="{{ href }}">Open</a>
            </article>
            HTML,
            [
                '{{ title }}' => escapeHtml($task['title']),
                '{{ status }}' => escapeHtml($status->label()),
                '{{ statusClass }}' => escapeHtml($status->status),
                '{{ description }}' => escapeHtml($task['description']),
                '{{ command }}' => escapeHtml($task['command']),
                '{{ href }}' => escapeHtml($href),
            ],
        );
    }

    $coverage = [];
    foreach ($families as $family) {
        $preview = __DIR__.'/output/'.$family['preview'];
        $coverage[] = strtr(
            '<li><span>{{ title }}</span><strong>{{ status }}</strong></li>',
            [
                '{{ title }}' => escapeHtml($family['title']),
                '{{ status }}' => escapeHtml(is_file($preview) && svgHasVisibleStyles($preview) ? 'full styled output' : 'missing styled output'),
            ],
        );
    }

    $content = implode("\n", $cards).'<section class="coverage"><h2>Diagram coverage</h2><ul>'.implode("\n", $coverage).'</ul></section>';

    writeFile($path, demoShellHtml(
        'Atelier Diagram Demo',
        'One local entry point for gallery artifacts, visual showcases, label review pages, and renderer experiments.',
        $content,
        null,
    ));
}

function demoShellHtml(string $title, string $description, string $content, ?string $backHref): string
{
    $back = null === $backHref ? '' : '<a class="back" href="'.escapeHtml($backHref).'">Back</a>';

    return strtr(
        <<<'HTML'
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>{{ title }}</title>
          <style>
            :root {
              color-scheme: light;
              --bg: #f7f8f5;
              --paper: #ffffff;
              --ink: #1f2937;
              --muted: #64748b;
              --line: rgba(15, 23, 42, 0.12);
              --accent: #256d5a;
              --accent-soft: #e4f1ec;
              --danger: #9f1239;
            }

            * {
              box-sizing: border-box;
            }

            body {
              margin: 0;
              background: var(--bg);
              color: var(--ink);
              font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
              -webkit-font-smoothing: antialiased;
            }

            main {
              width: min(1180px, calc(100vw - 40px));
              margin: 0 auto;
              padding: 36px 0 48px;
            }

            header.hero {
              display: grid;
              grid-template-columns: minmax(0, 1fr) auto;
              gap: 20px;
              align-items: start;
              margin-bottom: 24px;
            }

            h1, h2, p {
              margin: 0;
            }

            h1 {
              font-size: clamp(30px, 5vw, 54px);
              line-height: 0.95;
              letter-spacing: 0;
              text-wrap: balance;
            }

            h2 {
              font-size: 18px;
              line-height: 1.15;
              text-wrap: balance;
            }

            .hero p {
              max-width: 760px;
              margin-top: 12px;
              color: var(--muted);
              font-size: 16px;
              line-height: 1.5;
              text-wrap: pretty;
            }

            .back, .index-card a, .diagram-card nav a {
              min-height: 40px;
              display: inline-flex;
              align-items: center;
              justify-content: center;
              border-radius: 8px;
              background: var(--ink);
              color: #fff;
              padding: 0 14px;
              text-decoration: none;
              font-weight: 700;
              transition-property: transform, background-color;
              transition-duration: 140ms;
            }

            .back:active, .index-card a:active, .diagram-card nav a:active {
              transform: scale(0.96);
            }

            .grid {
              display: grid;
              grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
              gap: 16px;
            }

            .index-card, .diagram-card, .coverage {
              background: var(--paper);
              border-radius: 10px;
              box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 16px 40px rgba(15, 23, 42, 0.08);
            }

            .index-card {
              display: grid;
              gap: 14px;
              padding: 18px;
            }

            .index-card div, .diagram-card header {
              display: flex;
              justify-content: space-between;
              gap: 12px;
              align-items: start;
            }

            .index-card strong {
              font-size: 18px;
            }

            .index-card p {
              color: var(--muted);
              line-height: 1.45;
              text-wrap: pretty;
            }

            code {
              overflow-wrap: anywhere;
              border-radius: 8px;
              background: #f1f5f9;
              padding: 10px;
              color: #334155;
              font-size: 13px;
            }

            .status {
              border-radius: 999px;
              background: var(--accent-soft);
              color: var(--accent);
              padding: 4px 9px;
              font-size: 12px;
              font-weight: 800;
              white-space: nowrap;
            }

            .status.failed, .status.missing-output, .missing {
              color: var(--danger);
            }

            .status.skipped {
              color: var(--muted);
            }

            .coverage {
              grid-column: 1 / -1;
              padding: 18px;
            }

            .coverage ul {
              display: grid;
              grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
              gap: 8px 18px;
              padding: 0;
              margin: 14px 0 0;
              list-style: none;
            }

            .coverage li {
              display: flex;
              justify-content: space-between;
              gap: 12px;
              border-top: 1px solid var(--line);
              padding-top: 8px;
              font-size: 14px;
            }

            .coverage strong {
              color: var(--accent);
              font-size: 12px;
              text-align: right;
            }

            .diagram-card {
              display: grid;
              gap: 14px;
              padding: 14px;
            }

            .diagram-card header span {
              color: var(--accent);
              font-size: 12px;
              font-weight: 800;
              white-space: nowrap;
            }

            .preview {
              display: flex;
              align-items: center;
              justify-content: center;
              min-height: 220px;
              border-radius: 8px;
              background: #f8fafc;
              box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.1);
              overflow: hidden;
            }

            .preview img {
              display: block;
              width: 100%;
              height: 100%;
              max-height: 340px;
              object-fit: contain;
            }

            .diagram-card nav {
              display: flex;
              flex-wrap: wrap;
              gap: 8px;
              align-items: center;
            }

            .diagram-card nav a {
              background: var(--accent);
            }

            .muted {
              color: var(--muted);
              font-size: 13px;
            }

            @media (max-width: 720px) {
              main {
                width: min(100vw - 24px, 1180px);
                padding-top: 20px;
              }

              header.hero {
                grid-template-columns: 1fr;
              }
            }
          </style>
        </head>
        <body>
          <main>
            <header class="hero">
              <div>
                <h1>{{ title }}</h1>
                <p>{{ description }}</p>
              </div>
              {{ back }}
            </header>
            <section class="grid">
              {{ content }}
            </section>
          </main>
        </body>
        </html>
        HTML,
        [
            '{{ title }}' => escapeHtml($title),
            '{{ description }}' => escapeHtml($description),
            '{{ content }}' => $content,
            '{{ back }}' => $back,
        ],
    );
}

function outputHref(string $output): string
{
    return str_starts_with($output, 'examples/output/')
        ? substr($output, strlen('examples/output/'))
        : $output;
}

function svgHasVisibleStyles(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }

    $svg = file_get_contents($path);
    if (false === $svg) {
        return false;
    }

    return str_contains($svg, '<svg')
        && str_contains($svg, 'viewBox=')
        && (str_contains($svg, 'fill=') || str_contains($svg, 'stroke='))
        && str_contains($svg, 'font-family=');
}

/**
 * @param list<array{slug: string, title: string, preview: string, builder: string|null, parsed: string|null, markdown: string|null, textual: bool}> $families
 */
function assertGalleryCoverage(string $outputDir, array $families): void
{
    $missing = [];
    foreach ($families as $family) {
        foreach (['preview', 'builder'] as $key) {
            $file = $family[$key];
            if (!is_string($file) || !svgHasVisibleStyles($outputDir.'/'.$file)) {
                $missing[] = $family['title'].' '.$key.' styled SVG';
            }
        }
        if ($family['textual']) {
            foreach (['parsed', 'markdown'] as $key) {
                $file = $family[$key];
                if (!is_string($file) || !is_file($outputDir.'/'.$file)) {
                    $missing[] = $family['title'].' '.$key.' artifact';
                    continue;
                }
                if ('parsed' === $key && !svgHasVisibleStyles($outputDir.'/'.$file)) {
                    $missing[] = $family['title'].' parsed styled SVG';
                }
            }
        }
    }

    if ([] !== $missing) {
        throw new RuntimeException('Gallery coverage incomplete: '.implode(', ', $missing));
    }

    echo 'Verified gallery coverage for '.\count($families).' diagram families.'.\PHP_EOL;
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $path));
    }
}

function writeFile(string $path, string $contents): void
{
    ensureDirectory(dirname($path));
    if (false === file_put_contents($path, $contents)) {
        throw new RuntimeException(sprintf('Cannot write file: %s', $path));
    }
}

function removeDirectory(string $path): void
{
    $real = realpath($path);
    $expected = realpath(__DIR__).'/output';
    if (false === $real || $real !== $expected) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($real);
}

function openInBrowser(string $path): void
{
    if ('Darwin' !== \PHP_OS_FAMILY) {
        echo 'Open manually: '.$path.\PHP_EOL;

        return;
    }

    $process = proc_open(['open', $path], [], $pipes);
    if (is_resource($process)) {
        proc_close($process);
    }
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
}

final readonly class DemoRun
{
    /**
     * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
     */
    private function __construct(
        public array $task,
        public string $status,
        public string $output = '',
    ) {
    }

    /**
     * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
     */
    public static function generated(array $task, string $output): self
    {
        return new self($task, 'generated', $output);
    }

    /**
     * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
     */
    public static function skipped(array $task): self
    {
        return new self($task, 'skipped');
    }

    /**
     * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
     */
    public static function missing(array $task): self
    {
        return new self($task, 'missing-output', 'Script missing.');
    }

    /**
     * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
     */
    public static function missingOutput(array $task): self
    {
        return new self($task, 'missing-output', 'Output missing.');
    }

    /**
     * @param array{slug: string, title: string, command: string, script: string|null, output: string, description: string, optional?: bool} $task
     */
    public static function failed(array $task, string $output): self
    {
        return new self($task, 'failed', $output);
    }

    public function label(): string
    {
        return match ($this->status) {
            'generated' => 'generated',
            'skipped' => 'skipped',
            'missing-output' => 'missing',
            'failed' => 'failed',
            default => $this->status,
        };
    }
}
