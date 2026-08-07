<?php

declare(strict_types=1);

namespace Atelier\Diagram\Venn;

/**
 * One set of a Venn diagram.
 *
 * The id is one of A, B, C, assigned in declaration order by the builder.
 * The cardinality is carried by the model even though the schematic layout
 * ignores it -- a proportional layout can use it later.
 */
final readonly class VennSet
{
    public function __construct(
        public string $id,
        public string $label,
        public ?int $cardinality = null,
    ) {
    }
}
