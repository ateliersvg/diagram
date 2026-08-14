<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Text\CharWidthTextMeasurer;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextBlockMetrics;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Text\TextMetrics;

/**
 * Selects conservative metrics for theme fonts the default Helvetica table
 * cannot represent. In particular, every glyph in a monospace face occupies
 * the same advance, so narrow letters such as "i" must not shrink a node.
 */
final readonly class ThemeTextMeasurer implements TextMeasurerInterface
{
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

    public function wrap(string $text, float $maxWidth, float $fontSize, float $lineHeight = 1.2, bool $breakWords = false, FontWeight $weight = FontWeight::Normal): TextBlockMetrics
    {
        if ($maxWidth <= 0.0 || '' === trim($text)) {
            return new TextBlockMetrics([], 0.0, 0.0, 0.0, 0.0);
        }

        $lines = [];
        $current = '';
        $parts = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        assert(false !== $parts);

        foreach ($parts as $part) {
            $candidate = $current.$part;
            if ($this->measureLine($candidate, $fontSize, $weight)->width <= $maxWidth || '' === $current) {
                if ($this->measureLine($candidate, $fontSize, $weight)->width <= $maxWidth || !$breakWords) {
                    $current = $candidate;
                    continue;
                }
            }

            if ('' !== trim($current)) {
                $lines[] = trim($current);
            }
            $current = preg_match('/^\s+$/', $part) ? '' : $part;
        }

        if ('' !== trim($current)) {
            $lines[] = trim($current);
        }

        if ($breakWords) {
            $lines = $this->breakLongLines($lines, $maxWidth, $fontSize, $weight);
        }

        $width = 0.0;
        foreach ($lines as $line) {
            $width = max($width, $this->measureLine($line, $fontSize, $weight)->width);
        }

        $metrics = $this->measureLine('M', $fontSize, $weight);
        $lineBoxHeight = $fontSize * $lineHeight;
        $lastBaseline = [] === $lines ? 0.0 : $metrics->ascent + (\count($lines) - 1) * $lineBoxHeight;

        return new TextBlockMetrics(
            $lines,
            $width,
            \count($lines) * $lineBoxHeight,
            $metrics->ascent,
            $lastBaseline,
        );
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function breakLongLines(array $lines, float $maxWidth, float $fontSize, FontWeight $weight): array
    {
        $result = [];
        foreach ($lines as $line) {
            $current = '';
            foreach (mb_str_split($line) as $character) {
                $candidate = $current.$character;
                if ('' !== $current && $this->measureLine($candidate, $fontSize, $weight)->width > $maxWidth) {
                    $result[] = $current;
                    $current = $character;
                    continue;
                }
                $current = $candidate;
            }
            if ('' !== $current) {
                $result[] = $current;
            }
        }

        return $result;
    }
}
