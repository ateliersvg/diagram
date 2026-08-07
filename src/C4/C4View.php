<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

enum C4View: string
{
    case Context = 'C4Context';
    case Container = 'C4Container';
    case Component = 'C4Component';
}
