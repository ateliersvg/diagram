<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Venn;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Venn\VennDiagram;
use Atelier\Diagram\Venn\VennDiagramBuilder;
use Atelier\Diagram\Venn\VennSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VennDiagramBuilder::class)]
#[CoversClass(VennDiagram::class)]
#[CoversClass(VennSet::class)]
final class VennDiagramBuilderTest extends TestCase
{
    public function testBuildsSetsRegionLabelsTitleAndLegend(): void
    {
        $diagram = (new VennDiagramBuilder())
            ->title('Skills')
            ->set('Frontend', 12)
            ->set('Backend')
            ->regionLabel('AB', 'HTTP')
            ->withLegend()
            ->build();

        $this->assertSame('Skills', $diagram->title?->text);
        $this->assertSame('A', $diagram->sets[0]->id);
        $this->assertSame('Frontend', $diagram->sets[0]->label);
        $this->assertSame(12, $diagram->sets[0]->cardinality);
        $this->assertSame('B', $diagram->sets[1]->id);
        $this->assertNull($diagram->sets[1]->cardinality);
        $this->assertSame('HTTP', $diagram->regionLabels['AB']->text);
        $this->assertTrue($diagram->showLegend);
    }

    public function testAssignsSetIdsInDeclarationOrder(): void
    {
        $diagram = (new VennDiagramBuilder())
            ->set('One')
            ->set('Two')
            ->set('Three')
            ->build();

        $this->assertSame(['A', 'B', 'C'], array_map(static fn (VennSet $set): string => $set->id, $diagram->sets));
    }

    public function testLastRegionLabelWins(): void
    {
        $diagram = (new VennDiagramBuilder())
            ->set('Frontend')
            ->set('Backend')
            ->regionLabel('AB', 'first')
            ->regionLabel('AB', 'second')
            ->build();

        $this->assertSame('second', $diagram->regionLabels['AB']->text);
    }

    public function testCarriesTargetSizeAndPaddingOptions(): void
    {
        $diagram = (new VennDiagramBuilder())
            ->set('A')
            ->set('B')
            ->targetSize(640.0, 480.0)
            ->paddingPercent(10.0)
            ->innerPaddingPercent(5.0)
            ->circleStrokeWidth(2.0)
            ->build();

        $this->assertSame(640.0, $diagram->targetWidth);
        $this->assertSame(480.0, $diagram->targetHeight);
        $this->assertSame(10.0, $diagram->paddingPercent);
        $this->assertSame(5.0, $diagram->innerPaddingPercent);
        $this->assertSame(2.0, $diagram->circleStrokeWidth);
    }

    public function testRejectsMoreThanThreeSets(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('at most 3 sets');

        (new VennDiagramBuilder())
            ->set('A')
            ->set('B')
            ->set('C')
            ->set('D');
    }

    public function testRejectsUnknownRegion(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('Unknown Venn region "XY"');

        (new VennDiagramBuilder())->regionLabel('XY', 'oops');
    }

    public function testRejectsRegionLabelReferencingMissingSetOnBuild(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('references set "C"');

        (new VennDiagramBuilder())
            ->set('Frontend')
            ->set('Backend')
            ->regionLabel('ABC', 'center')
            ->build();
    }

    public function testRejectsFewerThanTwoSets(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('needs 2 or 3 sets, got 1');

        (new VennDiagramBuilder())
            ->set('Solo')
            ->build();
    }

    public function testRejectsPartialTargetSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs both width and height');

        new VennDiagram(
            [new VennSet('A', 'A'), new VennSet('B', 'B')],
            targetWidth: 100.0,
        );
    }

    public function testRejectsSetWithWrongId(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('Venn set #1 must have id "A", got "B"');

        new VennDiagram([new VennSet('B', 'First'), new VennSet('A', 'Second')]);
    }

    public function testRejectsUnknownRegionDirectly(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('Unknown Venn region "XY"');

        new VennDiagram(
            [new VennSet('A', 'A'), new VennSet('B', 'B')],
            regionLabels: ['XY' => new Label('oops')],
        );
    }

    public function testRejectsNonPositiveTargetSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venn target size dimensions must be positive');

        new VennDiagram(
            [new VennSet('A', 'A'), new VennSet('B', 'B')],
            targetWidth: 0.0,
            targetHeight: 480.0,
        );
    }

    public function testRejectsNegativePadding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venn padding percentages must not be negative');

        new VennDiagram(
            [new VennSet('A', 'A'), new VennSet('B', 'B')],
            paddingPercent: -1.0,
        );
    }

    public function testRejectsNegativeCircleStrokeWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venn circle stroke width must not be negative');

        new VennDiagram(
            [new VennSet('A', 'A'), new VennSet('B', 'B')],
            circleStrokeWidth: -1.0,
        );
    }
}
