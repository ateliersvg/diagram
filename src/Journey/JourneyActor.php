<?php

declare(strict_types=1);

namespace Atelier\Diagram\Journey;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class JourneyActor
{
    public function __construct(
        public string $name,
    ) {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Journey actor name must be non-empty.');
        }
    }
}
