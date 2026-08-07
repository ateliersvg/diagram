<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Theme;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\PatternKind;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Theme::class)]
final class ThemeTest extends TestCase
{
    public function testDefaultThemeHasSoberPalette(): void
    {
        $theme = Theme::default();

        $this->assertSame('#ffffff', $theme->backgroundColor);
        $this->assertSame('#1e293b', $theme->textColor);
        $this->assertCount(6, $theme->accentColors);
        $this->assertSame('Helvetica, Arial, sans-serif', $theme->fontFamily);
        $this->assertSame(14.0, $theme->fontSize);
        $this->assertSame(8.0, $theme->spacingUnit);
        $this->assertSame(1.5, $theme->strokeWidth);
        $this->assertNull($theme->minNodeWidth);
        $this->assertNull($theme->minNodeHeight);
    }

    public function testDarkThemeHasDeepBackgroundAndLightText(): void
    {
        $theme = Theme::dark();

        $this->assertSame('#020617', $theme->backgroundColor);
        $this->assertSame('#f8fafc', $theme->textColor);
        $this->assertSame('#67e8f9', $theme->nodeStrokeColor);
        $this->assertCount(6, $theme->accentColors);
        $this->assertSame('Helvetica, Arial, sans-serif', $theme->fontFamily);
        $this->assertSame(15.0, $theme->fontSize);
        $this->assertSame(9.0, $theme->spacingUnit);
        $this->assertSame(1.8, $theme->strokeWidth);
    }

    public function testBuiltInPaletteThemesHaveNoBackgroundPattern(): void
    {
        $this->assertNull(Theme::default()->backgroundPattern);
        $this->assertNull(Theme::dark()->backgroundPattern);
    }

    public function testBlueprintThemeCarriesAMinorMajorGrid(): void
    {
        $theme = Theme::blueprint();

        $this->assertSame('#06182b', $theme->backgroundColor);
        $this->assertSame('#ffffff', $theme->textColor);
        $this->assertStringContainsString('monospace', $theme->fontFamily);

        $pattern = $theme->backgroundPattern;
        $this->assertNotNull($pattern);
        $this->assertSame(PatternKind::Grid, $pattern->kind);
        $this->assertSame(16.0, $pattern->size);
        $this->assertTrue($pattern->hasMajor());
        $this->assertSame(80.0, $pattern->majorSize);
    }

    public function testMonoThemeIsGrayscaleWithoutPattern(): void
    {
        $theme = Theme::mono();

        $this->assertSame('#111111', $theme->textColor);
        $this->assertSame('#111111', $theme->nodeStrokeColor);
        $this->assertNull($theme->backgroundPattern);
    }

    public function testNeutralThemeIsWarmWithoutPattern(): void
    {
        $theme = Theme::neutral();

        $this->assertSame('#f5f3ef', $theme->backgroundColor);
        $this->assertNull($theme->backgroundPattern);
    }

    public function testRejectsNonPositiveStrokeWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('strokeWidth');

        new Theme('#fff', '#eee', '#333', '#111', '#666', ['#00f'], 'sans-serif', 14.0, 8.0, 0.0);
    }

    public function testRejectsEmptyAccentColors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('accentColors');

        new Theme('#fff', '#eee', '#333', '#111', '#666', [], 'sans-serif', 14.0, 8.0);
    }

    public function testRejectsNonPositiveFontSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fontSize');

        new Theme('#fff', '#eee', '#333', '#111', '#666', ['#00f'], 'sans-serif', 0.0, 8.0);
    }

    public function testRejectsNonPositiveSpacingUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('spacingUnit');

        new Theme('#fff', '#eee', '#333', '#111', '#666', ['#00f'], 'sans-serif', 14.0, -1.0);
    }

    public function testAcceptsOptionalMinimumNodeSize(): void
    {
        $theme = new Theme(
            '#fff',
            '#eee',
            '#333',
            '#111',
            '#666',
            ['#00f'],
            'sans-serif',
            14.0,
            8.0,
            minNodeWidth: 100.0,
            minNodeHeight: 88.0,
        );

        $this->assertSame(100.0, $theme->minNodeWidth);
        $this->assertSame(88.0, $theme->minNodeHeight);
    }

    public function testRejectsNonPositiveMinimumNodeWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minNodeWidth');

        new Theme('#fff', '#eee', '#333', '#111', '#666', ['#00f'], 'sans-serif', 14.0, 8.0, minNodeWidth: 0.0);
    }

    public function testRejectsNonPositiveMinimumNodeHeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minNodeHeight');

        new Theme('#fff', '#eee', '#333', '#111', '#666', ['#00f'], 'sans-serif', 14.0, 8.0, minNodeHeight: -1.0);
    }
}
