<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

/**
 * Free-form text label.
 *
 * Used for transition labels, Venn region labels, and commit labels.
 */
final readonly class Label
{
    public function __construct(
        public string $text,
    ) {
    }
}
