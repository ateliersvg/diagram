<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Safety limits applied before line-level parsing.
 */
final readonly class ParserInputLimits
{
    public const int DEFAULT_MAX_SOURCE_BYTES = 1_000_000;
    public const int DEFAULT_MAX_LINE_BYTES = 16_384;

    public function __construct(
        public int $maxSourceBytes = self::DEFAULT_MAX_SOURCE_BYTES,
        public int $maxLineBytes = self::DEFAULT_MAX_LINE_BYTES,
    ) {
        if ($maxSourceBytes < 1) {
            throw new InvalidArgumentException('Parser maxSourceBytes must be positive.');
        }
        if ($maxLineBytes < 1) {
            throw new InvalidArgumentException('Parser maxLineBytes must be positive.');
        }
    }

    public static function default(): self
    {
        return new self();
    }
}
