<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

enum SequenceBlockKind: string
{
    case Loop = 'loop';
    case Alt = 'alt';
    case Opt = 'opt';
    case Par = 'par';
}
