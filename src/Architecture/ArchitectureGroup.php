<?php

declare(strict_types=1);

namespace Atelier\Diagram\Architecture;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class ArchitectureGroup
{
    public function __construct(
        public string $id,
        public Label $label,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Architecture group id must be non-empty.');
        }
    }
}
