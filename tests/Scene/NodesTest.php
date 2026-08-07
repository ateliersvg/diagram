<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\GroupNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\OrientedTextNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RectNode::class)]
#[CoversClass(CircleNode::class)]
#[CoversClass(LineNode::class)]
#[CoversClass(PathNode::class)]
#[CoversClass(TextNode::class)]
#[CoversClass(OrientedTextNode::class)]
#[CoversClass(GroupNode::class)]
final class NodesTest extends TestCase
{
    public function testRectNodeExposesGeometryAndStyle(): void
    {
        $style = ShapeStyle::filled('#fff');
        $rect = new RectNode(1.0, 2.0, 3.0, 4.0, $style, cornerRadius: 0.5);

        $this->assertSame(1.0, $rect->x);
        $this->assertSame(2.0, $rect->y);
        $this->assertSame(3.0, $rect->width);
        $this->assertSame(4.0, $rect->height);
        $this->assertSame(0.5, $rect->cornerRadius);
        $this->assertSame($style, $rect->style);
    }

    public function testRectNodeCornerRadiusDefaultsToZero(): void
    {
        $rect = new RectNode(0.0, 0.0, 10.0, 10.0, ShapeStyle::filled('#fff'));

        $this->assertSame(0.0, $rect->cornerRadius);
    }

    public function testRectNodeRejectsNegativeDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RectNode dimensions');

        new RectNode(0.0, 0.0, -10.0, 10.0, ShapeStyle::filled('#fff'));
    }

    public function testRectNodeRejectsNegativeCornerRadius(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cornerRadius');

        new RectNode(0.0, 0.0, 10.0, 10.0, ShapeStyle::filled('#fff'), cornerRadius: -1.0);
    }

    public function testCircleNodeExposesGeometry(): void
    {
        $circle = new CircleNode(10.0, 20.0, 5.0, ShapeStyle::filled('#fff'));

        $this->assertSame(10.0, $circle->cx);
        $this->assertSame(20.0, $circle->cy);
        $this->assertSame(5.0, $circle->r);
    }

    public function testCircleNodeRejectsNegativeRadius(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CircleNode radius');

        new CircleNode(0.0, 0.0, -1.0, ShapeStyle::filled('#fff'));
    }

    public function testLineNodeExposesEndpoints(): void
    {
        $line = new LineNode(1.0, 2.0, 3.0, 4.0, ShapeStyle::stroked('#000'));

        $this->assertSame(1.0, $line->x1);
        $this->assertSame(2.0, $line->y1);
        $this->assertSame(3.0, $line->x2);
        $this->assertSame(4.0, $line->y2);
    }

    public function testPathNodeExposesData(): void
    {
        $path = new PathNode('M 0 0 L 10 10', ShapeStyle::stroked('#000'));

        $this->assertSame('M 0 0 L 10 10', $path->data);
    }

    public function testPathNodeRejectsEmptyData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PathNode data');

        new PathNode('   ', ShapeStyle::stroked('#000'));
    }

    public function testTextNodeExposesAnchorPointAndText(): void
    {
        $style = new TextStyle('Helvetica', 14.0);
        $text = new TextNode(5.0, 6.0, 'hello', $style);

        $this->assertSame(5.0, $text->x);
        $this->assertSame(6.0, $text->y);
        $this->assertSame('hello', $text->text);
        $this->assertSame($style, $text->style);
    }

    public function testOrientedTextNodeExposesRotationAndOutline(): void
    {
        $style = new TextStyle('Helvetica', 14.0);
        $text = new OrientedTextNode(5.0, 6.0, 'approve', $style, 18.0, '#fff', 4.0);

        $this->assertSame(5.0, $text->x);
        $this->assertSame(6.0, $text->y);
        $this->assertSame('approve', $text->text);
        $this->assertSame($style, $text->style);
        $this->assertSame(18.0, $text->rotationDegrees);
        $this->assertSame('#fff', $text->outlineColor);
        $this->assertSame(4.0, $text->outlineWidth);
    }

    public function testOrientedTextNodeRejectsNegativeOutlineWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outlineWidth');

        new OrientedTextNode(5.0, 6.0, 'approve', new TextStyle('Helvetica', 14.0), outlineWidth: -1.0);
    }

    public function testOrientedTextNodeRejectsEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OrientedTextNode text must not be empty.');

        new OrientedTextNode(5.0, 6.0, '   ', new TextStyle('Helvetica', 14.0));
    }

    public function testGroupNodeOpacityDefaultsToNull(): void
    {
        $group = new GroupNode([]);

        $this->assertSame([], $group->children);
        $this->assertNull($group->opacity);
    }

    public function testGroupNodeRejectsOpacityAboveOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GroupNode opacity');

        new GroupNode([], opacity: 1.5);
    }

    public function testGroupNodeRejectsNegativeOpacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GroupNode([], opacity: -0.1);
    }
}
