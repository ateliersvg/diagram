<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class C4Relationship
{
    public function __construct(
        public string $from,
        public string $to,
        public Label $label,
        public ?Label $technology = null,
    ) {
        if ('' === trim($from)) {
            throw new InvalidArgumentException('C4 relationship source id must be non-empty.');
        }
        if ('' === trim($to)) {
            throw new InvalidArgumentException('C4 relationship target id must be non-empty.');
        }
    }
}
