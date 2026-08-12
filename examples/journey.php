<?php

declare(strict_types=1);

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Journey\JourneyDiagram;

require_once dirname(__DIR__).'/vendor/autoload.php';

function buildJourneyDiagram(): JourneyDiagram
{
    return Diagram::journey()
        ->title('Checkout experience')
        ->section('Browse')
        ->task('Open product page', 5, ['Customer'])
        ->task('Add to cart', 4, ['Customer'])
        ->section('Payment')
        ->task('Enter card', 3, ['Customer', 'PSP'])
        ->task('Confirm order', 5, ['Customer'])
        ->build();
}

function journeyMermaid(): string
{
    return <<<'MERMAID'
        journey
            title Checkout experience
            section Browse
                Open product page: 5: Customer
                Add to cart: 4: Customer
            section Payment
                Enter card: 3: Customer, PSP
                Confirm order: 5: Customer
        MERMAID;
}

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (\is_string($scriptFilename) && basename(__FILE__) === basename($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    Diagram::of(buildJourneyDiagram())->saveSvg($outputDir.'/journey-builder.svg');
    Diagram::fromMermaid(journeyMermaid())->saveSvg($outputDir.'/journey-parsed.svg');
    file_put_contents($outputDir.'/journey.md', Diagram::of(buildJourneyDiagram())->toMarkdown());

    echo 'Wrote '.$outputDir.'/journey-builder.svg'.\PHP_EOL;
    echo 'Wrote '.$outputDir.'/journey-parsed.svg'.\PHP_EOL;
    echo 'Wrote '.$outputDir.'/journey.md'.\PHP_EOL;
}
