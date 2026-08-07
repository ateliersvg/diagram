<?php

declare(strict_types=1);

namespace Atelier\Diagram\Er;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class ErAttribute
{
    public function __construct(
        public string $type,
        public string $name,
    ) {
        if ('' === trim($type)) {
            throw new InvalidArgumentException('ER attribute type must be non-empty.');
        }
        if ('' === trim($name)) {
            throw new InvalidArgumentException('ER attribute name must be non-empty.');
        }
    }

    public function text(): string
    {
        return $this->type.' '.$this->name;
    }
}
