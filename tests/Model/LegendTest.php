<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Model;

use Atelier\Diagram\Model\Legend;
use Atelier\Diagram\Model\LegendEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Legend::class)]
#[CoversClass(LegendEntry::class)]
final class LegendTest extends TestCase
{
    public function testExposesEntriesInOrder(): void
    {
        $legend = new Legend([
            new LegendEntry('main', '#2563eb'),
            new LegendEntry('develop', '#dc2626'),
        ]);

        $this->assertCount(2, $legend->entries);
        $this->assertSame('main', $legend->entries[0]->label);
        $this->assertSame('#2563eb', $legend->entries[0]->color);
        $this->assertSame('develop', $legend->entries[1]->label);
        $this->assertSame('#dc2626', $legend->entries[1]->color);
    }

    public function testAllowsEmptyEntryList(): void
    {
        $legend = new Legend([]);

        $this->assertSame([], $legend->entries);
    }
}
