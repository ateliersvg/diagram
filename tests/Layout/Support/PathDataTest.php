<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Support;

use Atelier\Diagram\Layout\Support\PathData;
use Atelier\Layout\Connection\OrthogonalConnection;
use Atelier\Layout\Connection\Port;
use Atelier\Layout\Connection\PortSide;
use Atelier\Layout\Geometry\Point;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathData::class)]
final class PathDataTest extends TestCase
{
    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(PathData::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        // Invoke the guarded constructor body to confirm it is inert.
        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(PathData::class, $instance);
    }

    public function testNumberDropsTrailingZeros(): void
    {
        $this->assertSame('2', PathData::number(2.0));
        $this->assertSame('0', PathData::number(0.0));
        $this->assertSame('2.5', PathData::number(2.5));
        $this->assertSame('-0.5', PathData::number(-0.5));
    }

    public function testNumberRoundsToTwoDecimals(): void
    {
        $this->assertSame('1.23', PathData::number(1.234));
        $this->assertSame('1.24', PathData::number(1.236));
        $this->assertSame('-3.14', PathData::number(-3.14159));
    }

    public function testCubicBuildsMoveAndCurveCommands(): void
    {
        $this->assertSame(
            'M 0 0 C 1 2 3 4 5 6',
            PathData::cubic(0.0, 0.0, 1.0, 2.0, 3.0, 4.0, 5.0, 6.0),
        );
    }

    public function testConnectionBuildsMoveThenLineCommands(): void
    {
        $points = [new Point(0.0, 0.0), new Point(10.0, 0.0), new Point(10.0, 20.0)];
        $connection = new OrthogonalConnection(
            new Port($points[0], PortSide::Right),
            new Port($points[2], PortSide::Bottom),
            $points,
            OrthogonalConnection::segmentsForPoints($points),
            new Point(10.0, 0.0),
            new Point(0.0, 1.0),
        );

        $this->assertSame('M 0 0 L 10 0 L 10 20', PathData::connection($connection));
    }
}
