<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;

/**
 * Short source excerpt attached to parser diagnostics.
 */
final readonly class SourceExcerpt
{
    private const int MAX_CONTENT_BYTES = 120;

    public function __construct(
        public int $lineNumber,
        public string $content,
        public bool $truncated = false,
    ) {
    }

    public static function forLine(Line $line): self
    {
        return self::fromSource($line->number, $line->content);
    }

    public static function forSourceLine(SourceLine $line): self
    {
        return self::fromSource($line->number, $line->raw);
    }

    public static function fromSource(int $lineNumber, string $content): self
    {
        if (strlen($content) <= self::MAX_CONTENT_BYTES) {
            return new self($lineNumber, $content);
        }

        return new self($lineNumber, self::truncateContent($content), true);
    }

    private static function truncateContent(string $content): string
    {
        if (function_exists('mb_strcut')) {
            return mb_strcut($content, 0, self::MAX_CONTENT_BYTES - 3, 'UTF-8').'...';
        }

        $candidate = substr($content, 0, self::MAX_CONTENT_BYTES - 3);
        while ('' !== $candidate && !preg_match('//u', $candidate)) {
            $candidate = substr($candidate, 0, -1);
        }

        return $candidate.'...';
    }
}
