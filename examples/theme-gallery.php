<?php

declare(strict_types=1);

/*
 * Theme gallery: renders a fixed set of representative diagrams across every
 * built-in theme, side by side, into examples/output/theme-gallery/index.html.
 * Adding a Theme::* factory and listing it in $themes below is all it takes to
 * extend the comparison.
 */

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';

/** @return array<string, Diagram> */
function galleryDiagrams(): array
{
    $flow = Diagram::flowchart()
        ->direction(Direction::TopToBottom)
        ->title('Retrieval pipeline')
        ->node('Q', 'User Query')
        ->node('EX', 'Expansion')
        ->node('BM', 'BM25 / FTS5')
        ->node('VE', 'Vector Search')
        ->node('RRF', 'RRF Fusion')
        ->node('RR', 'LLM Re-ranking')
        ->edge('Q', 'EX')
        ->edge('Q', 'BM')
        ->edge('EX', 'VE')
        ->edge('BM', 'RRF')
        ->edge('VE', 'RRF')
        ->edge('RRF', 'RR', 'top 30')
        ->build();

    $mind = Diagram::mindmap()
        ->root('Marketing Content', 'root')
        ->child('root', 'Blog Posts', 'blog')
        ->child('blog', '+1500 words', 'blogw')
        ->child('root', 'Video', 'video')
        ->child('video', 'Most engaging', 'vid1')
        ->child('root', 'Webinars', 'web')
        ->child('web', 'Lead-gen', 'web1')
        ->child('root', 'Podcasts', 'pod')
        ->child('root', 'White Papers', 'wp')
        ->child('wp', 'Repurpose', 'wp1')
        ->build();

    $git = Diagram::git()
        ->commit('init')
        ->branch('feature')
        ->checkout('feature')
        ->commit('work')
        ->checkout('main')
        ->merge('feature')
        ->commit('release')
        ->build();

    return [
        'Flowchart' => Diagram::of($flow),
        'Mindmap' => Diagram::of($mind),
        'Git graph' => Diagram::of($git),
    ];
}

/** @return array<string, Theme> */
function galleryThemes(): array
{
    return [
        'default' => Theme::default(),
        'dark' => Theme::dark(),
        'blueprint' => Theme::blueprint(),
        'mono' => Theme::mono(),
        'neutral' => Theme::neutral(),
    ];
}

$outputDir = __DIR__.'/output/theme-gallery';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Cannot create output directory: '.$outputDir);
}

$diagrams = galleryDiagrams();
$themes = galleryThemes();

$rows = '';
foreach ($diagrams as $diagramName => $diagram) {
    $cells = '';
    foreach ($themes as $themeName => $theme) {
        $slug = strtolower($diagramName).'-'.$themeName;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        $file = $slug.'.svg';
        $diagram->saveSvg($outputDir.'/'.$file, $theme);
        $cells .= sprintf(
            '<figure><figcaption>%s</figcaption><img src="%s" alt="%s %s"></figure>',
            htmlspecialchars($themeName, \ENT_QUOTES),
            htmlspecialchars($file, \ENT_QUOTES),
            htmlspecialchars($diagramName, \ENT_QUOTES),
            htmlspecialchars($themeName, \ENT_QUOTES),
        );
    }
    $rows .= sprintf('<section><h2>%s</h2><div class="grid">%s</div></section>', htmlspecialchars($diagramName, \ENT_QUOTES), $cells);
}

$themeCols = count($themes);
$html = <<<HTML
<!doctype html>
<meta charset="utf-8">
<title>Theme gallery</title>
<style>
  body { font: 15px/1.5 Helvetica, Arial, sans-serif; margin: 2rem; background: #0b0f1a; color: #e2e8f0; }
  h1 { margin: 0 0 1.5rem; }
  section { margin-bottom: 2.5rem; }
  h2 { border-bottom: 1px solid #334155; padding-bottom: .4rem; }
  .grid { display: grid; grid-template-columns: repeat({$themeCols}, 1fr); gap: 1rem; }
  figure { margin: 0; background: #111827; border: 1px solid #1f2937; border-radius: 8px; padding: .75rem; }
  figcaption { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-bottom: .5rem; }
  img { width: 100%; height: auto; display: block; }
</style>
<h1>Theme gallery -- {$themeCols} themes</h1>
{$rows}
HTML;

$index = $outputDir.'/index.html';
file_put_contents($index, $html);

echo 'Wrote '.$index.\PHP_EOL;
