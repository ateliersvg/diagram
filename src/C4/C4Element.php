<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class C4Element
{
    public function __construct(
        public string $id,
        public C4ElementKind $kind,
        public Label $label,
        public ?Label $technology = null,
        public ?Label $description = null,
        public ?string $boundaryId = null,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('C4 element id must be non-empty.');
        }
        if (null !== $boundaryId && '' === trim($boundaryId)) {
            throw new InvalidArgumentException('C4 element boundary id must be non-empty.');
        }
    }
}
