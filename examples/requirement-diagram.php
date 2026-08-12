<?php

declare(strict_types=1);

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Requirement\RequirementDiagram;

require_once dirname(__DIR__).'/vendor/autoload.php';

function buildRequirementDiagram(): RequirementDiagram
{
    return Diagram::requirement()
        ->requirement('checkout', [
            'id' => 'REQ-1',
            'text' => 'Customer can checkout',
            'risk' => 'medium',
            'verifymethod' => 'test',
        ])
        ->requirement('receipt', [
            'id' => 'REQ-2',
            'text' => 'Customer receives receipt',
            'risk' => 'low',
            'verifymethod' => 'inspection',
        ])
        ->element('cart', [
            'type' => 'component',
        ])
        ->element('payment', [
            'type' => 'service',
        ])
        ->relationship('cart', 'satisfies', 'checkout')
        ->relationship('payment', 'verifies', 'receipt')
        ->relationship('checkout', 'contains', 'receipt')
        ->build();
}

function requirementDiagramMermaid(): string
{
    return <<<'MERMAID'
        requirementDiagram
            requirement checkout {
                id: REQ-1
                text: Customer can checkout
                risk: medium
                verifymethod: test
            }
            requirement receipt {
                id: REQ-2
                text: Customer receives receipt
                risk: low
                verifymethod: inspection
            }
            element cart {
                type: component
            }
            element payment {
                type: service
            }
            cart - satisfies -> checkout
            payment - verifies -> receipt
            checkout - contains -> receipt
        MERMAID;
}

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
if (\is_string($scriptFilename) && basename(__FILE__) === basename($scriptFilename)) {
    $outputDir = __DIR__.'/output';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $outputDir));
    }

    Diagram::of(buildRequirementDiagram())->saveSvg($outputDir.'/requirement-builder.svg');
    Diagram::fromMermaid(requirementDiagramMermaid())->saveSvg($outputDir.'/requirement-parsed.svg');
    file_put_contents($outputDir.'/requirement.md', Diagram::of(buildRequirementDiagram())->toMarkdown());

    echo 'Wrote '.$outputDir.'/requirement-builder.svg'.\PHP_EOL;
    echo 'Wrote '.$outputDir.'/requirement-parsed.svg'.\PHP_EOL;
    echo 'Wrote '.$outputDir.'/requirement.md'.\PHP_EOL;
}
