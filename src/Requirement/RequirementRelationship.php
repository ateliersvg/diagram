<?php

declare(strict_types=1);

namespace Atelier\Diagram\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class RequirementRelationship
{
    public function __construct(
        public string $from,
        public string $to,
        public RequirementRelationshipKind $kind,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Requirement relationship endpoints must be non-empty.');
        }
    }
}
