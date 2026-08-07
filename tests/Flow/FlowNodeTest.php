<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\FlowNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowNode::class)]
final class FlowNodeTest extends TestCase
{
    public function testBuildsNode(): void
    {
        $node = new FlowNode('A', 'Start');

        $this->assertSame('A', $node->id);
        $this->assertSame('Start', $node->label);
    }

    public function testRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('node id must be non-empty');

        new FlowNode('   ', 'Start');
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('node label must be non-empty');

        new FlowNode('A', '   ');
    }
}
