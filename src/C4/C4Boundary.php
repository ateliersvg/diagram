<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class C4Boundary
{
    public function __construct(
        public string $id,
        public Label $label,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('C4 boundary id must be non-empty.');
        }
    }
}
