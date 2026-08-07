<?php

declare(strict_types=1);

namespace Atelier\Diagram\Architecture;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class ArchitectureRelationship
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Label $label = null,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Architecture relationship endpoints must be non-empty.');
        }
    }
}
