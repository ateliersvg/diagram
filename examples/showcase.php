<?php

declare(strict_types=1);

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (\is_string($scriptFilename) && __FILE__ === realpath($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    $diagram = buildDarkSequenceShowcase();
    Diagram::of($diagram)->saveSvg($outputDir.'/sequence-dark-wow.svg', darkShowcaseTheme());
    file_put_contents($outputDir.'/sequence-dark-wow.md', Diagram::of($diagram)->toMarkdown());

    echo 'Wrote '.$outputDir.'/sequence-dark-wow.svg'.\PHP_EOL;
    echo 'Wrote '.$outputDir.'/sequence-dark-wow.md'.\PHP_EOL;
}

function buildDarkSequenceShowcase(): SequenceDiagram
{
    return (new SequenceDiagramBuilder())
        ->title('Incident response sequence')
        ->participant('Edge', 'Edge gateway')
        ->participant('Api', 'Public API')
        ->participant('Queue', 'Event queue')
        ->participant('Worker', 'Worker pool')
        ->participant('Ops', 'Ops console')
        ->message('Edge', 'Api', 'POST /checkout')
        ->message('Api', 'Queue', 'Publish order.created')
        ->activate('Queue')
        ->message('Queue', 'Worker', 'Deliver event')
        ->activate('Worker')
        ->block(SequenceBlockKind::Alt, 'inventory ok', 3, 7, [
            new SequenceBlockBranch('inventory ok', 3, 5),
            new SequenceBlockBranch('inventory degraded', 6, 7),
        ])
        ->message('Worker', 'Worker', 'Reserve stock')
        ->message('Worker', 'Api', 'Confirm reservation', MessageArrow::Dashed)
        ->message('Api', 'Edge', '202 Accepted', MessageArrow::Dashed)
        ->message('Worker', 'Ops', 'Open review task')
        ->message('Ops', 'Worker', 'Approve fallback')
        ->deactivate('Worker')
        ->deactivate('Queue')
        ->block(SequenceBlockKind::Loop, 'customer status polling', 8, 10)
        ->message('Edge', 'Api', 'GET /orders/{id}')
        ->message('Api', 'Worker', 'Read projection')
        ->message('Worker', 'Api', 'Projected status', MessageArrow::Dashed)
        ->message('Api', 'Edge', 'Ready for payment', MessageArrow::Dashed)
        ->build();
}

function darkShowcaseTheme(): Theme
{
    return Theme::dark();
}
