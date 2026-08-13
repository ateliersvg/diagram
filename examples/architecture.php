<?php

declare(strict_types=1);

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Diagram;

require_once dirname(__DIR__).'/vendor/autoload.php';

function buildArchitectureDiagram(): ArchitectureDiagram
{
    return Diagram::architecture()
        ->title('Checkout platform')
        ->group('Users', 'Users')
        ->person('Customer', 'Customer', 'Users')
        ->external('Stripe', 'Payment provider', 'Users')
        ->group('Web', 'Web tier')
        ->component('App', 'Frontend app', 'Web')
        ->component('Api', 'Checkout API', 'Web')
        ->queue('Events', 'Domain events', 'Web')
        ->group('Data', 'Data tier')
        ->database('Orders', 'Orders DB', 'Data')
        ->database('Ledger', 'Ledger DB', 'Data')
        ->relationship('Customer', 'App', 'uses')
        ->relationship('App', 'Api', 'calls')
        ->relationship('Api', 'Orders', 'writes')
        ->relationship('Api', 'Ledger', 'posts')
        ->relationship('Api', 'Stripe', 'charges')
        ->relationship('Api', 'Events', 'publishes')
        ->build();
}

function architectureMermaid(): string
{
    return <<<'MERMAID'
        architecture
            title Checkout platform
            group Users [Users]
                person Customer [Customer]
                external Stripe [Payment provider]
            group Web [Web tier]
                component App [Frontend app]
                component Api [Checkout API]
                queue Events [Domain events]
            group Data [Data tier]
                database Orders [Orders DB]
                database Ledger [Ledger DB]
            Customer -> App : uses
            App -> Api : calls
            Api -> Orders : writes
            Api -> Ledger : posts
            Api -> Stripe : charges
            Api -> Events : publishes
        MERMAID;
}

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (\is_string($scriptFilename) && __FILE__ === realpath($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    $targets = [
        'architecture-builder.svg' => Diagram::of(buildArchitectureDiagram()),
        'architecture-parsed.svg' => Diagram::fromMermaid(architectureMermaid()),
    ];

    foreach ($targets as $name => $diagram) {
        $target = $outputDir.'/'.$name;
        $diagram->saveSvg($target);
        echo 'Wrote '.$target.\PHP_EOL;
    }

    $target = $outputDir.'/architecture.md';
    file_put_contents($target, Diagram::of(buildArchitectureDiagram())->toMarkdown());
    echo 'Wrote '.$target.\PHP_EOL;
}
