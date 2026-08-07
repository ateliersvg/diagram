<?php

declare(strict_types=1);

namespace Atelier\Diagram\Architecture;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class ArchitectureNode
{
    public function __construct(
        public string $id,
        public ArchitectureNodeKind $kind,
        public Label $label,
        public ?string $groupId = null,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Architecture node id must be non-empty.');
        }
    }
}
