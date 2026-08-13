<?php

declare(strict_types=1);

/*
 * Part-2.2 visual review artifact: lays out the reference state machine
 * via the fluent builder (TopToBottom and LeftToRight) and via the Mermaid
 * parser, writing three SVGs to examples/output/.
 *
 * state-tb.svg is frozen as the layout snapshot; state-parsed.svg must be
 * byte-identical to the untitled TopToBottom builder render (equivalence
 * test in tests/Parser/StateDiagramParserTest.php).
 */

use Atelier\Diagram\Layout\State\StateLayoutEngine;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Parser\StateDiagramParser;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/state-machine.php';

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
    throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
}

$engine = new StateLayoutEngine();
$renderer = new SvgRenderer();
$theme = Theme::default();

$targets = [
    'state-tb.svg' => buildStateMachineDiagram(Direction::TopToBottom, 'Order lifecycle'),
    'state-lr.svg' => buildStateMachineDiagram(Direction::LeftToRight, 'Order lifecycle'),
    'state-parsed.svg' => (new StateDiagramParser())->parse(stateMachineMermaid()),
];

foreach ($targets as $name => $diagram) {
    $target = $outputDir.'/'.$name;
    file_put_contents($target, $renderer->render($engine->layout($diagram, $theme)));
    echo 'Wrote '.$target.\PHP_EOL;
}
