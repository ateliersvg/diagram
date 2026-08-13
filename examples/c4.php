<?php

declare(strict_types=1);

use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\Diagram;

require_once dirname(__DIR__).'/vendor/autoload.php';

function buildC4Diagram(): C4Diagram
{
    return Diagram::c4()
        ->containerView()
        ->title('Shop platform')
        ->person('buyer', 'Buyer', 'Places and tracks orders')
        ->externalSystem('stripe', 'Stripe', 'Payment provider')
        ->externalSystem('warehouse', 'Warehouse', 'Ships accepted orders')
        ->boundary('shop', 'Shop Platform')
            ->container('web', 'Web App', 'Symfony', 'Customer checkout UI')
            ->container('api', 'API', 'PHP', 'Order orchestration')
            ->database('db', 'Orders DB', 'PostgreSQL', 'Stores order state')
        ->endBoundary()
        ->relationship('buyer', 'web', 'uses')
        ->relationship('web', 'api', 'submits checkout', 'HTTPS')
        ->relationship('api', 'db', 'reads and writes')
        ->relationship('api', 'stripe', 'charges card', 'HTTPS')
        ->relationship('api', 'warehouse', 'creates shipment')
        ->build();
}

function c4Mermaid(): string
{
    return <<<'MERMAID'
C4Container
    title Shop platform
    Person(buyer, "Buyer", "Places and tracks orders")
    System_Ext(stripe, "Stripe", "Payment provider")
    System_Ext(warehouse, "Warehouse", "Ships accepted orders")
    System_Boundary(shop, "Shop Platform") {
        Container(web, "Web App", "Symfony", "Customer checkout UI")
        Container(api, "API", "PHP", "Order orchestration")
        ContainerDb(db, "Orders DB", "PostgreSQL", "Stores order state")
    }
    Rel(buyer, web, "uses")
    Rel(web, api, "submits checkout", "HTTPS")
    Rel(api, db, "reads and writes")
    Rel(api, stripe, "charges card", "HTTPS")
    Rel(api, warehouse, "creates shipment")
MERMAID;
}

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (!is_string($scriptFilename)) {
    $scriptFilename = '';
}

if (basename(__FILE__) === basename($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    Diagram::of(buildC4Diagram())->saveSvg($outputDir.'/c4-builder.svg');
    Diagram::fromMermaid(c4Mermaid())->saveSvg($outputDir.'/c4-parsed.svg');
    file_put_contents($outputDir.'/c4.md', Diagram::of(buildC4Diagram())->toMarkdown());
}
