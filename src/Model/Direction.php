<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

/**
 * Flow direction of a diagram.
 *
 * Mapping the Mermaid shorthands (TB / LR) belongs to parsers.
 */
enum Direction
{
    case TopToBottom;
    case LeftToRight;
}
