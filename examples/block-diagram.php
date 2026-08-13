<?php

declare(strict_types=1);

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\Diagram;

require_once dirname(__DIR__).'/vendor/autoload.php';

function buildBlockDiagram(): BlockDiagram
{
    return (new BlockDiagramBuilder())
        ->title('Layout kernel')
        ->block('Solver', 'LayoutSolver')
        ->block('Grid', 'Grid')
        ->block('Text', 'TextBlock')
        ->beginGroup('Primitives', 'Primitives')
            ->block('Rect', 'Rect')
            ->block('Insets', 'Insets')
        ->endGroup()
        ->relationship('Solver', 'Grid', 'solves')
        ->relationship('Solver', 'Text', 'measures')
        ->relationship('Grid', 'Rect', 'places')
        ->relationship('Text', 'Insets', 'respects')
        ->build();
}

function blockDiagramMermaid(): string
{
    return <<<'MERMAID'
        block
            title Layout kernel
            block Solver [LayoutSolver]
            block Grid [Grid]
            block Text [TextBlock]
            group Primitives [Primitives]
                block Rect [Rect]
                block Insets [Insets]
            end
            Solver -> Grid : solves
            Solver -> Text : measures
            Grid -> Rect : places
            Text -> Insets : respects
        MERMAID;
}

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (\is_string($scriptFilename) && __FILE__ === realpath($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    $targets = [
        'block-builder.svg' => Diagram::of(buildBlockDiagram()),
        'block-parsed.svg' => Diagram::fromMermaid(blockDiagramMermaid()),
    ];

    foreach ($targets as $name => $diagram) {
        $target = $outputDir.'/'.$name;
        $diagram->saveSvg($target);
        echo 'Wrote '.$target.\PHP_EOL;
    }

    $target = $outputDir.'/block.md';
    file_put_contents($target, Diagram::of(buildBlockDiagram())->toMarkdown());
    echo 'Wrote '.$target.\PHP_EOL;
}
