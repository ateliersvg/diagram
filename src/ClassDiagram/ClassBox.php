<?php

declare(strict_types=1);

namespace Atelier\Diagram\ClassDiagram;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class ClassBox
{
    /**
     * @param list<ClassMember> $members
     */
    public function __construct(
        public string $id,
        public array $members = [],
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Class id must be non-empty.');
        }
    }
}
