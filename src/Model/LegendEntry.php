<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

/**
 * One legend line: a label paired with a swatch color.
 */
final readonly class LegendEntry
{
    public function __construct(
        public string $label,
        public string $color,
    ) {
    }
}
