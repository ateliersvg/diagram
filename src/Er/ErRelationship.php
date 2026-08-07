<?php

declare(strict_types=1);

namespace Atelier\Diagram\Er;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final readonly class ErRelationship
{
    public function __construct(
        public string $from,
        public ErCardinality $fromCardinality,
        public string $to,
        public ErCardinality $toCardinality,
        public ?Label $label = null,
    ) {
        if ('' === trim($from) || '' === trim($to)) {
            throw new InvalidArgumentException('ER relationship endpoints must be non-empty.');
        }
    }

    public function edgeToken(): string
    {
        return $this->fromCardinality->value.'--'.$this->toCardinality->value;
    }
}
