<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Parser\Support\LineBreakScanner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

/**
 * Iterates significant lines while preserving raw indentation.
 *
 * Blank lines and lines whose trimmed content starts with `%%` are skipped.
 *
 * @implements \IteratorAggregate<int, SourceLine>
 */
final class SourceLineStream implements \IteratorAggregate
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
     * @return \Generator<int, SourceLine>
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

            $line = new SourceLine($number, $raw);
            if ('' !== $line->trimmed && !str_starts_with($line->trimmed, '%%')) {
                yield $line;
            }

            ++$number;
        }
    }

    /**
     * @return list<SourceLine>
     */
    public function toArray(): array
    {
        return iterator_to_array($this, false);
    }
}
