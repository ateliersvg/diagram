<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Support;

use Atelier\Diagram\Layout\Support\LegendArtist;
use Atelier\Diagram\Model\Legend;
use Atelier\Diagram\Model\LegendEntry;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextBlockMetrics;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Text\TextMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegendArtist::class)]
final class LegendArtistTest extends TestCase
{
    private function measurer(): TextMeasurerInterface
    {
        return new class implements TextMeasurerInterface {
            public function measureLine(string $text, float $fontSize, FontWeight $weight = FontWeight::Normal): TextMetrics
            {
                return new TextMetrics(20.0, 10.0, 8.0);
            }

            public function wrap(string $text, float $maxWidth, float $fontSize, float $lineHeight = 1.2, bool $breakWords = false, FontWeight $weight = FontWeight::Normal): TextBlockMetrics
            {
                $line = $this->measureLine($text, $fontSize, $weight);

                return new TextBlockMetrics([$text], $line->width, $line->height, $line->ascent, $line->ascent);
            }
        };
    }

    public function testMeasureReturnsZeroForEmptyLegend(): void
    {
        $artist = new LegendArtist($this->measurer());

        $this->assertSame([0.0, 0.0], $artist->measure(new Legend([]), Theme::default()));
    }

    public function testMeasureReturnsBlockDimensions(): void
    {
        $artist = new LegendArtist($this->measurer());
        $legend = new Legend([
            new LegendEntry('main', '#2563eb'),
            new LegendEntry('dev', '#dc2626'),
        ]);

        // Two rows. Row width = swatch 12 + labelGap 8 + label 20 = 40.
        // Row height = max(swatch 12, label 10) = 12. Row gap = 0.5 * 8 = 4.
        // Total height = 12 + 4 + 12 = 28.
        $this->assertSame([40.0, 28.0], $artist->measure($legend, Theme::default()));
    }

    public function testNodesPlacesSwatchAndLabelPerEntry(): void
    {
        $artist = new LegendArtist($this->measurer());
        $legend = new Legend([
            new LegendEntry('main', '#2563eb'),
            new LegendEntry('dev', '#dc2626'),
        ]);

        $nodes = $artist->nodes($legend, Theme::default(), 100.0, 200.0);

        $this->assertCount(4, $nodes);

        $swatch0 = $nodes[0];
        $this->assertInstanceOf(RectNode::class, $swatch0);
        $this->assertSame(100.0, $swatch0->x);
        $this->assertSame(200.0, $swatch0->y);
        $this->assertSame(12.0, $swatch0->width);
        $this->assertSame(12.0, $swatch0->height);
        $this->assertSame(2.0, $swatch0->cornerRadius);
        $this->assertSame('#2563eb', $swatch0->style->fill);

        $label0 = $nodes[1];
        $this->assertInstanceOf(TextNode::class, $label0);
        // labelFrame.x = swatch.right 112 + labelGap 8 = 120.
        $this->assertSame(120.0, $label0->x);
        // labelFrame.y = 200 + (12 - 10) / 2 = 201, plus ascent 8 = 209.
        $this->assertSame(209.0, $label0->y);
        $this->assertSame('main', $label0->text);
        $this->assertSame('#64748b', $label0->style->fill);

        $swatch1 = $nodes[2];
        $this->assertInstanceOf(RectNode::class, $swatch1);
        // Second row top = 200 + rowHeight 12 + gap 4 = 216.
        $this->assertSame(216.0, $swatch1->y);
        $this->assertSame('#dc2626', $swatch1->style->fill);

        $label1 = $nodes[3];
        $this->assertInstanceOf(TextNode::class, $label1);
        $this->assertSame('dev', $label1->text);
        $this->assertSame(225.0, $label1->y);
    }
}
