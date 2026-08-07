<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

/**
 * Shape of a background pattern tile.
 */
enum PatternKind: string
{
    case Grid = 'grid';
    case Dots = 'dots';
}
