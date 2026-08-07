<?php

declare(strict_types=1);

namespace Atelier\Diagram\Block;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class BlockRelationship
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Label $label = null,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('Block relationship endpoints must be non-empty.');
        }
    }
}
