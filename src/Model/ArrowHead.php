<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

/**
 * Arrowhead drawn at the end of an edge.
 */
enum ArrowHead
{
    case None;

    /** Filled triangle. */
    case Arrow;

    /** Chevron. */
    case Open;
}
