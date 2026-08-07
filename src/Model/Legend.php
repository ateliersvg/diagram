<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

/**
 * Diagram legend: an ordered list of label/color entries.
 *
 * Diagrams may build one automatically (git: branch -> color) or accept a
 * user-provided one.
 */
final readonly class Legend
{
    /**
     * @param list<LegendEntry> $entries
     */
    public function __construct(
        public array $entries,
    ) {
    }
}
