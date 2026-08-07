<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Support;

use Atelier\Diagram\Layout\Support\TitleArtist;
use Atelier\Diagram\Model\Title;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextBlockMetrics;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Text\TextMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TitleArtist::class)]
final class TitleArtistTest extends TestCase
{
    private function measurer(): TextMeasurerInterface
    {
        return new class implements TextMeasurerInterface {
            public function measureLine(string $text, float $fontSize, FontWeight $weight = FontWeight::Normal): TextMetrics
            {
                return new TextMetrics(50.0, 18.0, 14.0);
            }

            public function wrap(string $text, float $maxWidth, float $fontSize, float $lineHeight = 1.2, bool $breakWords = false, FontWeight $weight = FontWeight::Normal): TextBlockMetrics
            {
                $line = $this->measureLine($text, $fontSize, $weight);

                return new TextBlockMetrics([$text], $line->width, $line->height, $line->ascent, $line->ascent);
            }
        };
    }

    public function testFontSizeScalesThemeFontSize(): void
    {
        $artist = new TitleArtist($this->measurer());

        // 1.15 * 14 = 16.1.
        $this->assertEqualsWithDelta(16.1, $artist->fontSize(Theme::default()), 1e-9);
    }

    public function testMeasureReturnsLineMetrics(): void
    {
        $artist = new TitleArtist($this->measurer());

        $metrics = $artist->measure(new Title('Pipeline'), Theme::default());

        $this->assertSame(50.0, $metrics->width);
        $this->assertSame(18.0, $metrics->height);
        $this->assertSame(14.0, $metrics->ascent);
    }

    public function testBlockHeightIsZeroWithoutTitle(): void
    {
        $artist = new TitleArtist($this->measurer());

        $this->assertSame(0.0, $artist->blockHeight(null, Theme::default()));
    }

    public function testBlockHeightAddsGapBelowTitle(): void
    {
        $artist = new TitleArtist($this->measurer());

        // Line box 18 + gap 2 * spacingUnit 8 = 34.
        $this->assertSame(34.0, $artist->blockHeight(new Title('Pipeline'), Theme::default()));
    }

    public function testNodeCentersTitleAndOffsetsBaselineByAscent(): void
    {
        $artist = new TitleArtist($this->measurer());

        $node = $artist->node(new Title('Pipeline'), Theme::default(), 120.0, 40.0);

        $this->assertSame(120.0, $node->x);
        // top 40 + ascent 14 = 54.
        $this->assertSame(54.0, $node->y);
        $this->assertSame('Pipeline', $node->text);
        $this->assertSame(FontWeight::Bold, $node->style->fontWeight);
        $this->assertSame(TextAnchor::Middle, $node->style->anchor);
        $this->assertSame('#1e293b', $node->style->fill);
    }
}
