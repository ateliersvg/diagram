<?php

declare(strict_types=1);

/*
 * Section-2.3 visual review artifact: renders the reference git history
 * twice: once hand-built through GitGraphBuilder (with a title, exercising
 * the title path), once parsed from the equivalent Mermaid gitGraph source
 * (untitled, since the grammar has no title statement), and writes both SVGs:
 *
 *   examples/output/git-builder.svg   (titled, frozen by GitLayoutEngineTest)
 *   examples/output/git-parsed.svg    (must be byte-identical to the
 *                                      untitled builder render, frozen by
 *                                      GitGraphParserTest)
 */

use Atelier\Diagram\Layout\Git\GitLayoutEngine;
use Atelier\Diagram\Parser\GitGraphParser;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/git-history.php';

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

$theme = Theme::default();
$engine = new GitLayoutEngine();
$renderer = new SvgRenderer();

$builderTarget = $outputDir.'/git-builder.svg';
file_put_contents($builderTarget, $renderer->render($engine->layout(buildGitHistory('Release history'), $theme)));
echo 'Wrote '.$builderTarget.\PHP_EOL;

$parsedTarget = $outputDir.'/git-parsed.svg';
file_put_contents($parsedTarget, $renderer->render($engine->layout((new GitGraphParser())->parse(gitHistoryMermaid()), $theme)));
echo 'Wrote '.$parsedTarget.\PHP_EOL;
