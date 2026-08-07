<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\BackgroundPattern;
use Atelier\Diagram\Scene\PatternKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackgroundPattern::class)]
#[CoversClass(PatternKind::class)]
final class BackgroundPatternTest extends TestCase
{
    public function testGridPatternKeepsItsFields(): void
    {
        $pattern = new BackgroundPattern(PatternKind::Grid, '#67e8f9', size: 16.0, lineWidth: 0.45, opacity: 0.26);

        $this->assertSame(PatternKind::Grid, $pattern->kind);
        $this->assertSame('#67e8f9', $pattern->color);
        $this->assertSame(16.0, $pattern->size);
        $this->assertSame(0.45, $pattern->lineWidth);
        $this->assertSame(0.26, $pattern->opacity);
        $this->assertFalse($pattern->hasMajor());
    }

    public function testMajorLayerIsDetectedWhenColorAndSizeSet(): void
    {
        $pattern = new BackgroundPattern(
            PatternKind::Grid,
            '#67e8f9',
            majorColor: '#e6fbff',
            majorSize: 80.0,
        );

        $this->assertTrue($pattern->hasMajor());
        $this->assertSame('#e6fbff', $pattern->majorColor);
        $this->assertSame(80.0, $pattern->majorSize);
    }

    public function testMajorLayerIsAbsentWhenSizeMissing(): void
    {
        $pattern = new BackgroundPattern(PatternKind::Grid, '#67e8f9', majorColor: '#e6fbff');

        $this->assertFalse($pattern->hasMajor());
    }

    public function testRejectsNonPositiveSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackgroundPattern(PatternKind::Grid, '#67e8f9', size: 0.0);
    }

    public function testRejectsNonPositiveLineWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackgroundPattern(PatternKind::Grid, '#67e8f9', lineWidth: 0.0);
    }

    public function testRejectsOpacityOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackgroundPattern(PatternKind::Grid, '#67e8f9', opacity: 1.5);
    }

    public function testRejectsNegativeMajorSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackgroundPattern(PatternKind::Grid, '#67e8f9', majorSize: -1.0);
    }

    public function testRejectsNonPositiveMajorLineWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackgroundPattern(PatternKind::Grid, '#67e8f9', majorLineWidth: 0.0);
    }

    public function testRejectsMajorOpacityOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackgroundPattern(PatternKind::Grid, '#67e8f9', majorOpacity: -0.1);
    }
}
