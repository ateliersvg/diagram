<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\FlowEdge;
use Atelier\Diagram\Model\Label;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowEdge::class)]
final class FlowEdgeTest extends TestCase
{
    public function testBuildsDirectedEdge(): void
    {
        $edge = new FlowEdge('A', 'B', new Label('go'));

        $this->assertSame('A', $edge->from);
        $this->assertSame('B', $edge->to);
        $this->assertSame('go', $edge->label?->text);
    }

    public function testRejectsEmptyFrom(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('endpoints must be non-empty');

        new FlowEdge('   ', 'B');
    }

    public function testRejectsEmptyTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('endpoints must be non-empty');

        new FlowEdge('A', '   ');
    }
}
