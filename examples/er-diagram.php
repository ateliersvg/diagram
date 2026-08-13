<?php

declare(strict_types=1);

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErDiagramBuilder;

require_once dirname(__DIR__).'/vendor/autoload.php';

function buildErDiagram(): ErDiagram
{
    return (new ErDiagramBuilder())
        ->attribute('CUSTOMER', 'int', 'id')
        ->attribute('CUSTOMER', 'string', 'name')
        ->attribute('CUSTOMER', 'string', 'email')
        ->attribute('ORDER', 'int', 'id')
        ->attribute('ORDER', 'date', 'placedAt')
        ->attribute('ORDER', 'decimal', 'total')
        ->attribute('ORDER_ITEM', 'int', 'quantity')
        ->attribute('ORDER_ITEM', 'decimal', 'unitPrice')
        ->attribute('PRODUCT', 'int', 'id')
        ->attribute('PRODUCT', 'string', 'sku')
        ->attribute('PRODUCT', 'string', 'name')
        ->attribute('PAYMENT', 'int', 'id')
        ->attribute('PAYMENT', 'decimal', 'amount')
        ->attribute('SHIPMENT', 'int', 'id')
        ->attribute('SHIPMENT', 'string', 'trackingNumber')
        ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
        ->relationship('ORDER', '||', 'ORDER_ITEM', '|{', 'contains')
        ->relationship('PRODUCT', '||', 'ORDER_ITEM', 'o{', 'appears in')
        ->relationship('ORDER', '||', 'PAYMENT', 'o{', 'paid by')
        ->relationship('ORDER', '||', 'SHIPMENT', '|o', 'ships as')
        ->build();
}

function erDiagramMermaid(): string
{
    return <<<'MERMAID'
        erDiagram
            CUSTOMER {
                int id
                string name
                string email
            }
            ORDER {
                int id
                date placedAt
                decimal total
            }
            ORDER_ITEM {
                int quantity
                decimal unitPrice
            }
            PRODUCT {
                int id
                string sku
                string name
            }
            PAYMENT {
                int id
                decimal amount
            }
            SHIPMENT {
                int id
                string trackingNumber
            }
            CUSTOMER ||--o{ ORDER : places
            ORDER ||--|{ ORDER_ITEM : contains
            PRODUCT ||--o{ ORDER_ITEM : appears in
            ORDER ||--o{ PAYMENT : paid by
            ORDER ||--|o SHIPMENT : ships as
        MERMAID;
}

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (\is_string($scriptFilename) && __FILE__ === realpath($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    $targets = [
        'er-builder.svg' => Diagram::of(buildErDiagram()),
        'er-parsed.svg' => Diagram::fromMermaid(erDiagramMermaid()),
    ];

    foreach ($targets as $name => $diagram) {
        $target = $outputDir.'/'.$name;
        $diagram->saveSvg($target);
        echo 'Wrote '.$target.\PHP_EOL;
    }

    $target = $outputDir.'/er.md';
    file_put_contents($target, Diagram::of(buildErDiagram())->toMarkdown());
    echo 'Wrote '.$target.\PHP_EOL;
}
