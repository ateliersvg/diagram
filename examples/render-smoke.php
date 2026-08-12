<?php

declare(strict_types=1);

/*
 * Part-1 visual review artifact: builds a Scene by hand exercising every
 * node type and both styles, writes examples/output/scene-smoke.svg.
 *
 * The snapshot test in tests/Renderer/Svg/SvgRendererTest.php freezes the
 * renderer output of this exact scene.
 */

use Atelier\Diagram\Renderer\Svg\SvgRenderer;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/smoke-scene.php';

$scene = buildSmokeScene();

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

$target = $outputDir.'/scene-smoke.svg';
file_put_contents($target, (new SvgRenderer())->render($scene));

echo 'Wrote '.$target.\PHP_EOL;
