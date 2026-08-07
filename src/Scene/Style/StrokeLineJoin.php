<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene\Style;

enum StrokeLineJoin: string
{
    case Miter = 'miter';
    case Round = 'round';
    case Bevel = 'bevel';
}
