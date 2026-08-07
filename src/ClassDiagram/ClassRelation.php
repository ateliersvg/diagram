<?php

declare(strict_types=1);

namespace Atelier\Diagram\ClassDiagram;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class ClassRelation
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Label $label = null,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Class relation endpoints must be non-empty.');
        }
    }
}
