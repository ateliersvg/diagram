<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

enum MessageArrow: string
{
    case Solid = '->>';
    case Dashed = '-->>';
}
