<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;

/**
 * Tracks section lines whose required children are validated at boundaries.
 */
final class SectionContentTracker
{
    private ?Line $currentLine = null;

    private ?string $currentTitle = null;

    private int $currentItemCount = 0;

    public function __construct(
        private readonly string $emptySectionMessage,
    ) {
    }

    public function begin(Line $line, string $title): void
    {
        $this->assertCurrentNotEmpty();
        $this->currentLine = $line;
        $this->currentTitle = $title;
        $this->currentItemCount = 0;
    }

    public function touch(): void
    {
        ++$this->currentItemCount;
    }

    public function assertClosed(): void
    {
        $this->assertCurrentNotEmpty();
    }

    private function assertCurrentNotEmpty(): void
    {
        if (null === $this->currentLine || null === $this->currentTitle || $this->currentItemCount > 0) {
            return;
        }

        throw ParseErrors::wrap($this->currentLine, new InvalidArgumentException(\sprintf($this->emptySectionMessage, $this->currentTitle)));
    }
}
