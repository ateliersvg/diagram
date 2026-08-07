<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Scene\Style;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\StrokeLineCap;
use Atelier\Diagram\Scene\Style\StrokeLineJoin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShapeStyle::class)]
final class ShapeStyleTest extends TestCase
{
    public function testDefaultsToNoPaintSolidHairline(): void
    {
        $style = new ShapeStyle();

        $this->assertNull($style->fill);
        $this->assertNull($style->stroke);
        $this->assertSame(1.0, $style->strokeWidth);
        $this->assertSame(LineStyle::Solid, $style->lineStyle);
        $this->assertNull($style->opacity);
        $this->assertNull($style->strokeLineCap);
        $this->assertNull($style->strokeLineJoin);
    }

    public function testFilledCreatesFillOnlyStyle(): void
    {
        $style = ShapeStyle::filled('#ff0000');

        $this->assertSame('#ff0000', $style->fill);
        $this->assertNull($style->stroke);
    }

    public function testStrokedCreatesStrokeOnlyStyle(): void
    {
        $style = ShapeStyle::stroked('#00ff00', 2.5);

        $this->assertNull($style->fill);
        $this->assertSame('#00ff00', $style->stroke);
        $this->assertSame(2.5, $style->strokeWidth);
        $this->assertSame(StrokeLineCap::Round, $style->strokeLineCap);
        $this->assertSame(StrokeLineJoin::Round, $style->strokeLineJoin);
    }

    public function testStrokedDefaultsToWidthOne(): void
    {
        $style = ShapeStyle::stroked('#00ff00');

        $this->assertSame(1.0, $style->strokeWidth);
    }

    public function testRejectsNegativeStrokeWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('strokeWidth');

        new ShapeStyle(strokeWidth: -1.0);
    }

    public function testRejectsOutOfRangeOpacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('opacity');

        new ShapeStyle(opacity: 2.0);
    }
}
