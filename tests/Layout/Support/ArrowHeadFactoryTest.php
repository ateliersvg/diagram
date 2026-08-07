<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Support;

use Atelier\Diagram\Layout\Support\ArrowHeadFactory;
use Atelier\Diagram\Model\ArrowHead;
use Atelier\Diagram\Scene\PathNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrowHeadFactory::class)]
final class ArrowHeadFactoryTest extends TestCase
{
    public function testNodeReturnsNullForNone(): void
    {
        $factory = new ArrowHeadFactory();

        $this->assertNull($factory->node(ArrowHead::None, 10.0, 10.0, 1.0, 0.0, 4.0, 2.0, '#000000'));
    }

    public function testNodeBuildsFilledTriangleForArrow(): void
    {
        $factory = new ArrowHeadFactory();

        $node = $factory->node(ArrowHead::Arrow, 10.0, 10.0, 1.0, 0.0, 4.0, 2.0, '#ff0000');

        $this->assertInstanceOf(PathNode::class, $node);
        $this->assertSame('M 10 10 L 6 12 L 6 8 Z', $node->data);
        $this->assertSame('#ff0000', $node->style->fill);
        $this->assertNull($node->style->stroke);
    }

    public function testNodeBuildsStrokedChevronForOpen(): void
    {
        $factory = new ArrowHeadFactory();

        $node = $factory->node(ArrowHead::Open, 10.0, 10.0, 1.0, 0.0, 4.0, 2.0, '#00ff00', 1.5);

        $this->assertInstanceOf(PathNode::class, $node);
        $this->assertSame('M 6 12 L 10 10 L 6 8', $node->data);
        $this->assertSame('#00ff00', $node->style->stroke);
        $this->assertNull($node->style->fill);
        $this->assertSame(1.5, $node->style->strokeWidth);
    }

    public function testArrowGeometryForRightwardDirection(): void
    {
        $factory = new ArrowHeadFactory();

        $node = $factory->arrow(10.0, 10.0, 1.0, 0.0, 4.0, 2.0, '#123456');

        $this->assertSame('M 10 10 L 6 12 L 6 8 Z', $node->data);
        $this->assertSame('#123456', $node->style->fill);
    }

    public function testArrowNormalizesNonUnitDirection(): void
    {
        $factory = new ArrowHeadFactory();

        // Direction (0, 2) normalizes to (0, 1): the head points straight down.
        $node = $factory->arrow(5.0, 5.0, 0.0, 2.0, 3.0, 1.0, '#000000');

        $this->assertSame('M 5 5 L 4 2 L 6 2 Z', $node->data);
    }

    public function testOpenGeometryForRightwardDirection(): void
    {
        $factory = new ArrowHeadFactory();

        $node = $factory->open(10.0, 10.0, 1.0, 0.0, 4.0, 2.0, '#abcdef', 2.0);

        $this->assertSame('M 6 12 L 10 10 L 6 8', $node->data);
        $this->assertSame('#abcdef', $node->style->stroke);
        $this->assertSame(2.0, $node->style->strokeWidth);
    }

    public function testDegenerateDirectionFallsBackToPointingDown(): void
    {
        $factory = new ArrowHeadFactory();

        $node = $factory->arrow(5.0, 5.0, 0.0, 0.0, 3.0, 1.0, '#000000');

        $this->assertSame('M 5 5 L 4 2 L 6 2 Z', $node->data);
    }
}
