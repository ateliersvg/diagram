<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Parser\Line;

/**
 * Stack for line-based block grammars (`subgraph`, `loop`, `alt`, ...).
 */
final class BlockScanner
{
    /**
     * @var list<BlockFrame>
     */
    private array $stack = [];

    private ?Line $lastLine = null;

    public function observe(Line $line): void
    {
        $this->lastLine = $line;
    }

    public function begin(string $kind, string $id, string $label, Line $line, ?int $startIndex = null): BlockFrame
    {
        $this->observe($line);

        $parent = $this->current();
        $frame = new BlockFrame(
            kind: $kind,
            id: $id,
            label: $label,
            startLine: $line,
            startIndex: $startIndex,
            parentId: $parent?->id,
            depth: \count($this->stack),
        );
        $this->stack[] = $frame;

        return $frame;
    }

    public function end(Line $line, string $grammar): BlockSpan
    {
        $this->observe($line);

        $frame = array_pop($this->stack);
        if (!$frame instanceof BlockFrame) {
            throw ParseErrors::unexpectedBlockEnd($line, $grammar);
        }

        return new BlockSpan(
            kind: $frame->kind,
            id: $frame->id,
            label: $frame->label,
            startLine: $frame->startLine,
            endLine: $line,
            startIndex: $frame->startIndex,
            parentId: $frame->parentId,
            depth: $frame->depth,
            touchedValues: $frame->touchedValues(),
        );
    }

    public function endExpected(Line $line, string $grammar, string $expectedKind): BlockSpan
    {
        $this->assertCurrentKind($line, $grammar, $expectedKind);

        return $this->end($line, $grammar);
    }

    public function current(): ?BlockFrame
    {
        $key = array_key_last($this->stack);

        return null !== $key ? $this->stack[$key] : null;
    }

    public function depth(): int
    {
        return \count($this->stack);
    }

    public function assertCurrentKind(Line $line, string $grammar, string $expectedKind): BlockFrame
    {
        $this->observe($line);

        $frame = $this->current();
        if (!$frame instanceof BlockFrame) {
            throw ParseErrors::expectedOpenBlock($line, $grammar, $expectedKind);
        }

        if ($expectedKind !== $frame->kind) {
            throw ParseErrors::expectedBlockKind($line, $grammar, $expectedKind, $frame->kind);
        }

        return $frame;
    }

    /**
     * @return list<BlockFrame>
     */
    public function frames(): array
    {
        return $this->stack;
    }

    public function touchAll(string $value): void
    {
        foreach ($this->stack as $frame) {
            $frame->touch($value);
        }
    }

    public function lastLine(): ?Line
    {
        return $this->lastLine;
    }

    public function assertClosed(string $grammar): void
    {
        $frame = $this->current();
        if (null !== $frame) {
            throw ParseErrors::unclosedBlock($frame->startLine, $grammar.' block', $this->lastLine);
        }
    }
}
