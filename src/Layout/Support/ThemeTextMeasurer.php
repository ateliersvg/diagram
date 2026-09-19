<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Text\TextMetrics;
use Atelier\Layout\Text\WrapsText;

/**
 * Selects conservative metrics for theme fonts the default Helvetica table
 * cannot represent. In particular, every glyph in a monospace face occupies
 * the same advance, so narrow letters such as "i" must not shrink a node.
 */
final readonly class ThemeTextMeasurer implements TextMeasurerInterface
{
    use WrapsText;

    private const float MONOSPACE_ADVANCE = 0.64;

    private function __construct(
        private TextMeasurerInterface $fallback,
        private float $minimumAdvance,
    ) {
    }

    public static function for(Theme $theme): TextMeasurerInterface
    {
        $fallback = new CharWidthTextMeasurer(heightFactor: 1.4, ascentFactor: 0.92);

        if (!str_contains(strtolower($theme->fontFamily), 'monospace')) {
            return $fallback;
        }

        return new self($fallback, self::MONOSPACE_ADVANCE);
    }

    public function measureLine(string $text, float $fontSize, FontWeight $weight = FontWeight::Normal): TextMetrics
    {
        $metrics = $this->fallback->measureLine($text, $fontSize, $weight);
        $minimumWidth = mb_strlen($text) * $fontSize * $this->minimumAdvance;

        return new TextMetrics(max($metrics->width, $minimumWidth), $metrics->height, $metrics->ascent);
    }
}
