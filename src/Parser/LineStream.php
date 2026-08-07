<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Parser\Support\LineBreakScanner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

/**
 * Iterates the significant lines of a Mermaid source text.
 *
 * Lines are trimmed; blank lines and `%%` comment lines are skipped.
 * Line numbers are 1-based and refer to the original source, so parse
 * errors can point at the exact input line. Shared by all grammar parsers.
 *
 * @implements \IteratorAggregate<int, Line>
 */
final class LineStream implements \IteratorAggregate
{
    private readonly ParserInputLimits $limits;

    public function __construct(
        private readonly string $source,
        ?ParserInputLimits $limits = null,
        private readonly int $startOffset = 0,
        private readonly int $startLineNumber = 1,
    ) {
        $this->limits = $limits ?? ParserInputLimits::default();
    }

    /**
     * @return \Generator<int, Line>
     */
    public function getIterator(): \Generator
    {
        $sourceBytes = strlen($this->source);
        if ($sourceBytes > $this->limits->maxSourceBytes) {
            throw ParseErrors::sourceTooLarge($sourceBytes, $this->limits->maxSourceBytes);
        }

        $number = $this->startLineNumber;
        $offset = $this->startOffset;
        $length = strlen($this->source);

        while (null !== ($line = LineBreakScanner::read($this->source, $offset, $length))) {
            [$raw, $offset] = $line;

            if (strlen($raw) > $this->limits->maxLineBytes) {
                throw ParseErrors::lineTooLong($number, $raw, $this->limits->maxLineBytes);
            }

            $content = trim($raw);
            if ('' !== $content && !str_starts_with($content, '%%')) {
                yield new Line($number, $content);
            }

            ++$number;
        }
    }

    /**
     * Collects all significant lines into an array.
     *
     * @return list<Line>
     */
    public function toArray(): array
    {
        return iterator_to_array($this, false);
    }
}
