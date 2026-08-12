<?php

declare(strict_types=1);

use Atelier\Diagram\Venn\VennDiagram;
use Atelier\Diagram\Venn\VennDiagramBuilder;

/**
 * Builds the 2.1 reference diagrams: a 2-set Venn with title, the three
 * addressable regions labeled and the opt-in set legend, and a 3-set Venn
 * with title and all seven regions labeled (no legend).
 *
 * Shared between examples/venn.php and the layout snapshot test.
 */
function buildVenn2(): VennDiagram
{
    return (new VennDiagramBuilder())
        ->title('Web skills')
        ->set('Frontend', 42)
        ->set('Backend', 35)
        ->regionLabel('A', 'CSS')
        ->regionLabel('B', 'SQL')
        ->regionLabel('AB', 'HTTP')
        ->withLegend()
        ->build();
}

function buildVenn3(): VennDiagram
{
    return (new VennDiagramBuilder())
        ->title('Pick two')
        ->set('Fast', 12)
        ->set('Good', 8)
        ->set('Cheap', 20)
        ->regionLabel('A', 'rushed')
        ->regionLabel('B', 'gold-plated')
        ->regionLabel('C', 'bargain')
        ->regionLabel('AB', 'pricey')
        ->regionLabel('AC', 'fragile')
        ->regionLabel('BC', 'slow')
        ->regionLabel('ABC', 'myth')
        ->build();
}
