<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout\Support;

use Atelier\Diagram\Model\Title;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Text\FontWeight;
use Atelier\Layout\Text\TextMeasurerInterface;
use Atelier\Layout\Text\TextMetrics;

/**
 * Places diagram titles uniformly across layout engines.
 *
 * Every diagram title is bold, 1.15x the theme font size, horizontally
 * centered at the top of the canvas, with a 2-spacing-unit gap before the
 * content below it.
 *
 * @internal
 */
final class TitleArtist
{
    /** Title font size, as a factor of the theme font size. */
    private const float FONT_SCALE = 1.15;

    /** Gap between the title line box and the content, in spacing units. */
    private const float GAP_UNITS = 2.0;

    public function __construct(
        private readonly TextMeasurerInterface $textMeasurer,
    ) {
    }

    public function fontSize(Theme $theme): float
    {
        return self::FONT_SCALE * $theme->fontSize;
    }

    public function measure(Title $title, Theme $theme): TextMetrics
    {
        return $this->textMeasurer->measureLine($title->text, $this->fontSize($theme), FontWeight::Bold);
    }

    /**
     * Vertical room the title occupies above the content: the title line
     * box plus the gap below it, 0 when there is no title.
     */
    public function blockHeight(?Title $title, Theme $theme): float
    {
        if (null === $title) {
            return 0.0;
        }

        return $this->measure($title, $theme)->height + self::GAP_UNITS * $theme->spacingUnit;
    }

    /**
     * The title text node, centered on $centerX, line box starting at $top.
     */
    public function node(Title $title, Theme $theme, float $centerX, float $top): TextNode
    {
        return new TextNode(
            $centerX,
            $top + $this->measure($title, $theme)->ascent,
            $title->text,
            new TextStyle($theme->fontFamily, $this->fontSize($theme), FontWeight::Bold, TextAnchor::Middle, $theme->textColor),
        );
    }
}
