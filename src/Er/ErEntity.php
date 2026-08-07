<?php

declare(strict_types=1);

namespace Atelier\Diagram\Er;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class ErEntity
{
    /**
     * @param list<ErAttribute> $attributes
     */
    public function __construct(
        public string $id,
        public array $attributes = [],
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('ER entity id must be non-empty.');
        }
    }
}
