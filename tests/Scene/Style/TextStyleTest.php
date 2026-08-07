<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Scene\Style;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Layout\Text\FontWeight;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextStyle::class)]
final class TextStyleTest extends TestCase
{
    public function testDefaultsToNormalStartBlack(): void
    {
        $style = new TextStyle('Helvetica, Arial, sans-serif', 14.0);

        $this->assertSame('Helvetica, Arial, sans-serif', $style->fontFamily);
        $this->assertSame(14.0, $style->fontSize);
        $this->assertSame(FontWeight::Normal, $style->fontWeight);
        $this->assertSame(TextAnchor::Start, $style->anchor);
        $this->assertSame('#000000', $style->fill);
    }

    public function testExposesAllFields(): void
    {
        $style = new TextStyle('monospace', 12.0, FontWeight::Bold, TextAnchor::End, '#64748b');

        $this->assertSame(FontWeight::Bold, $style->fontWeight);
        $this->assertSame(TextAnchor::End, $style->anchor);
        $this->assertSame('#64748b', $style->fill);
    }

    public function testRejectsNonPositiveFontSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fontSize');

        new TextStyle('Helvetica', 0.0);
    }
}
