<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Support;

use Atelier\Diagram\Layout\Support\ThemeTextMeasurer;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThemeTextMeasurer::class)]
final class ThemeTextMeasurerTest extends TestCase
{
    public function testBlueprintUsesTheFullMonospaceAdvanceForNarrowCharacters(): void
    {
        $theme = Theme::blueprint();
        $metrics = ThemeTextMeasurer::for($theme)->measureLine('iiiiiiii', $theme->fontSize);

        $this->assertGreaterThanOrEqual(8 * $theme->fontSize * 0.64, $metrics->width);
    }

    public function testBlueprintWrapsUsingMonospaceAdvances(): void
    {
        $theme = Theme::blueprint();
        $measurer = ThemeTextMeasurer::for($theme);
        $block = $measurer->wrap('iiii iiii', 5 * $theme->fontSize * 0.64, $theme->fontSize);

        $this->assertSame(['iiii', 'iiii'], $block->lines);
    }

    public function testProportionalThemesKeepProportionalMetrics(): void
    {
        $theme = Theme::default();
        $metrics = ThemeTextMeasurer::for($theme)->measureLine('iiiiiiii', $theme->fontSize);

        $this->assertLessThan(8 * $theme->fontSize * 0.5, $metrics->width);
    }
}
