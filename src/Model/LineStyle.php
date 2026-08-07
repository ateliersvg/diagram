<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

/**
 * Stroke pattern of a line or shape outline.
 *
 * Renderers derive dash patterns from it (Dashed -> "6 4", Dotted -> "2 3",
 * scaled by stroke width).
 */
enum LineStyle
{
    case Solid;
    case Dashed;
    case Dotted;
}
