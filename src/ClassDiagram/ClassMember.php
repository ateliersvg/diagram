<?php

declare(strict_types=1);

namespace Atelier\Diagram\ClassDiagram;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class ClassMember
{
    public function __construct(
        public string $text,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('Class member text must be non-empty.');
        }
    }
}
