<?php

declare(strict_types=1);

/*
 * Renders the order-lifecycle state machine and the two-feature git history
 * back to markdown through MarkdownRenderer, writing the fenced Mermaid
 * blocks to examples/output/ and printing them.
 *
 * The models are built untitled: the supported Mermaid grammar has no
 * title statement, so an untitled model round-trips exactly.
 */

use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/state-machine.php';
require __DIR__.'/git-history.php';

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

$renderer = new MarkdownRenderer();

$targets = [
    'state.md' => buildStateMachineDiagram(),
    'git.md' => buildGitHistory(),
];

foreach ($targets as $name => $diagram) {
    $target = $outputDir.'/'.$name;
    $markdown = $renderer->render($diagram);
    file_put_contents($target, $markdown);
    echo 'Wrote '.$target.\PHP_EOL.\PHP_EOL.$markdown.\PHP_EOL;
}
