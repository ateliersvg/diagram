<?php

declare(strict_types=1);

namespace Atelier\Diagram\Model;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Diagram title.
 *
 * Optional in every diagram model.
 */
final readonly class Title
{
    public function __construct(
        public string $text,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('Title text must be a non-empty string.');
        }
    }
}
