<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\BackgroundPattern;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\GroupNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\PatternKind;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scene::class)]
final class SceneTest extends TestCase
{
    public function testExposesDimensionsBackgroundAndNodes(): void
    {
        $scene = new Scene(640.0, 480.0, '#ffffff', [
            new RectNode(10.0, 10.0, 100.0, 50.0, ShapeStyle::filled('#f1f5f9')),
        ]);

        $this->assertSame(640.0, $scene->width);
        $this->assertSame(480.0, $scene->height);
        $this->assertSame('#ffffff', $scene->backgroundColor);
        $this->assertCount(1, $scene->nodes);
    }

    public function testExposesOptionalMetadata(): void
    {
        $scene = new Scene(100.0, 80.0, title: 'Checkout flow', description: 'Flowchart rendered by atelier/diagram.');

        $this->assertSame('Checkout flow', $scene->title);
        $this->assertSame('Flowchart rendered by atelier/diagram.', $scene->description);
    }

    public function testBackgroundAndNodesDefaultToTransparentAndEmpty(): void
    {
        $scene = new Scene(100.0, 100.0);

        $this->assertNull($scene->backgroundColor);
        $this->assertSame([], $scene->nodes);
    }

    public function testRejectsNonPositiveWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scene dimensions must be positive');

        new Scene(0.0, 100.0);
    }

    public function testRejectsNegativeHeight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Scene(100.0, -1.0);
    }

    public function testRejectsEmptyMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Scene(100.0, 100.0, title: ' ');
    }

    public function testHoldsAMixedNodeTree(): void
    {
        $shape = ShapeStyle::stroked('#334155', 2.0);
        $text = new TextStyle('Helvetica', 14.0, anchor: TextAnchor::Middle, fill: '#1e293b');

        $scene = new Scene(200.0, 200.0, null, [
            new GroupNode([
                new RectNode(10.0, 10.0, 80.0, 40.0, $shape, cornerRadius: 4.0),
                new TextNode(50.0, 35.0, 'Idle', $text),
            ], opacity: 0.9),
            new CircleNode(150.0, 30.0, 12.0, $shape),
            new LineNode(50.0, 50.0, 150.0, 42.0, $shape),
            new PathNode('M 10 100 L 50 150 Z', $shape),
        ]);

        $this->assertCount(4, $scene->nodes);
        $group = $scene->nodes[0];
        $this->assertInstanceOf(GroupNode::class, $group);
        $this->assertCount(2, $group->children);
        $this->assertSame(0.9, $group->opacity);
    }

    public function testRejectsEmptyDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('description');

        new Scene(100.0, 100.0, null, [], 'Title', '   ');
    }

    public function testBackgroundPatternDefaultsToNull(): void
    {
        $scene = new Scene(100.0, 100.0);

        $this->assertNull($scene->backgroundPattern);
    }

    public function testWithBackgroundPatternReturnsCopyCarryingThePattern(): void
    {
        $scene = new Scene(120.0, 90.0, '#020617', [new CircleNode(10.0, 10.0, 5.0, ShapeStyle::filled('#fff'))], 'Title', 'Desc');
        $pattern = new BackgroundPattern(PatternKind::Grid, '#67e8f9');

        $themed = $scene->withBackgroundPattern($pattern);

        $this->assertNull($scene->backgroundPattern, 'original scene stays untouched');
        $this->assertSame($pattern, $themed->backgroundPattern);
        $this->assertSame(120.0, $themed->width);
        $this->assertSame(90.0, $themed->height);
        $this->assertSame('#020617', $themed->backgroundColor);
        $this->assertCount(1, $themed->nodes);
        $this->assertSame('Title', $themed->title);
        $this->assertSame('Desc', $themed->description);
    }
}
