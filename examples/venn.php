<?php

declare(strict_types=1);

/*
 * Section 2.1 visual review artifact: builds the reference 2-set and 3-set
 * Venn diagrams, lays them out with the default theme, and writes
 * examples/output/venn-2.svg and examples/output/venn-3.svg.
 *
 * The snapshot test in tests/Layout/Venn/VennLayoutEngineTest.php freezes
 * the rendered output of these exact diagrams.
 */

use Atelier\Diagram\Layout\Venn\VennLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/venn-diagrams.php';

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

$engine = new VennLayoutEngine();
$renderer = new SvgRenderer();
$theme = Theme::default();

foreach (['venn-2.svg' => buildVenn2(), 'venn-3.svg' => buildVenn3()] as $file => $diagram) {
    $target = $outputDir.'/'.$file;
    file_put_contents($target, $renderer->render($engine->layout($diagram, $theme)));
    echo 'Wrote '.$target.\PHP_EOL;
}
