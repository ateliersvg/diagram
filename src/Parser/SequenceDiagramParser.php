<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Support\BlockFrame;
use Atelier\Diagram\Parser\Support\BlockScanner;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\Support\IdentifierPattern;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserDiagnosticCode;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;

/**
 * Parses the first supported Mermaid sequence diagram subset:
 *
 *     sequenceDiagram
 *     title Checkout
 *     participant A
 *     participant B as Backend
 *     A->>B: Request
 *     B-->>A: Response
 *
 * Unsupported Mermaid sequence features (actors, notes, nested blocks,
 * activation, opt/par blocks, autonumber) fail fast with a ParseException. The
 * implementation intentionally goes through the public builder API.
 *
 * @implements MermaidDiagramParserInterface<SequenceDiagram>
 */
final class SequenceDiagramParser implements MermaidDiagramParserInterface
{
    private const string BLOCK = 'sequence.block';
    private const string BRANCH = 'sequence.branch';
    private const string TITLE = 'sequence.title';
    private const string PARTICIPANT = 'sequence.participant';
    private const string ACTIVATE = 'sequence.activate';
    private const string DEACTIVATE = 'sequence.deactivate';
    private const string MESSAGE = 'sequence.message';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): SequenceDiagram
    {
        $builder = new SequenceDiagramBuilder();
        $blocks = new BlockScanner();
        $branchStarts = [];
        $branchLabels = [];
        $branchLines = [];
        $blockIndex = 0;

        $header ??= HeaderParser::firstSignificantLine($source, $limits);
        $headerLine = $header->line;
        HeaderParser::exact($headerLine, ['sequenceDiagram'], '"sequenceDiagram"');

        foreach (new LineStream($source, $limits, $header->nextOffset, $headerLine->number + 1) as $line) {
            $blocks->observe($line);

            if (null !== $match = StatementMatcher::for($line)->matchRule(self::rule(self::BLOCK, '/^(loop|alt|opt|par)\s+(.+)$/'))) {
                if (null !== $blocks->current()) {
                    throw ParseErrors::syntax($line, 'Nested sequence blocks are not supported', ParserDiagnosticCode::NestedBlock);
                }
                $label = ParserValues::nonEmptyLabel($line, $match->get(2), 'Sequence block');
                $frame = $blocks->begin($match->get(1), 'block'.(++$blockIndex), $label, $line, $builder->messageCount());
                $branchStarts[$frame->id] = [$builder->messageCount()];
                $branchLabels[$frame->id] = [$label];
                $branchLines[$frame->id] = [$line];
                continue;
            }

            if (null !== $match = StatementMatcher::for($line)->matchRule(self::rule(self::BRANCH, '/^(else|and)\s+(.+)$/'))) {
                $frame = $this->branchFrame($blocks, $line, $match->get(1));
                $label = ParserValues::nonEmptyLabel($line, $match->get(2), 'Sequence branch');
                $this->assertCurrentBranchHasMessages($frame->id, $branchStarts, $builder->messageCount(), $frame->startLine);
                $branchStarts[$frame->id][] = $builder->messageCount();
                $branchLabels[$frame->id][] = $label;
                $branchLines[$frame->id][] = $line;
                continue;
            }

            if ('end' === $line->content) {
                $frame = $blocks->current();
                $span = null !== $frame ? $blocks->endExpected($line, 'sequence', $frame->kind) : $blocks->end($line, 'sequence');
                $last = $builder->messageCount() - 1;
                if (null === $span->startIndex || $last < $span->startIndex) {
                    throw ParseErrors::syntax($span->startLine, 'Sequence block must contain at least one message', ParserDiagnosticCode::EmptyBlock);
                }
                $branches = $this->branches($span->id, $branchLabels, $branchStarts, $branchLines, $last, $span->startLine);
                $builder->block(SequenceBlockKind::from($span->kind), $span->label, $span->startIndex, $last, $branches);
                continue;
            }

            try {
                $this->parseLine($builder, $line);
            } catch (InvalidArgumentException $exception) {
                throw ParseErrors::wrap($line, $exception);
            }
        }

        $blocks->assertClosed('sequence');

        try {
            return $builder->build();
        } catch (InvalidArgumentException $exception) {
            throw ParseErrors::wrap($headerLine, $exception);
        }
    }

    private function parseLine(SequenceDiagramBuilder $builder, Line $line): void
    {
        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmpty($line, $match->get(1), 'Sequence title'));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::PARTICIPANT, '/^participant\s+('.$id.')(?:\s+as\s+(.+))?$/'))) {
            $label = ParserValues::optionalNonEmptyLabel($line, $match->optional(2), 'Participant');
            $builder->participant($match->get(1), $label);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::ACTIVATE, '/^activate\s+('.$id.')$/'))) {
            $builder->activate($match->get(1));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::DEACTIVATE, '/^deactivate\s+('.$id.')$/'))) {
            $builder->deactivate($match->get(1));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::MESSAGE, '/^('.$id.')\s*(-->>|->>)\s*('.$id.')\s*:\s*(.*)$/'))) {
            $label = ParserValues::nonEmptyLabel($line, $match->get(4), 'Sequence message');
            $builder->message($match->get(1), $match->get(3), $label, MessageArrow::from($match->get(2)));

            return;
        }

        throw ParseErrors::unsupported($line, 'sequence diagram');
    }

    private function branchFrame(BlockScanner $blocks, Line $line, string $branch): BlockFrame
    {
        $expected = match ($branch) {
            'else' => SequenceBlockKind::Alt->value,
            'and' => SequenceBlockKind::Par->value,
            default => throw ParseErrors::syntax($line, \sprintf('Unsupported sequence branch "%s"', $branch), ParserDiagnosticCode::UnsupportedBranch),
        };

        try {
            return $blocks->assertCurrentKind($line, 'sequence', $expected);
        } catch (ParseException) {
            if (null === $blocks->current()) {
                throw ParseErrors::syntax($line, \sprintf('Unexpected sequence "%s"', $branch), ParserDiagnosticCode::UnexpectedBranch);
            }

            throw ParseErrors::syntax($line, \sprintf('Sequence "%s" is only supported inside %s blocks', $branch, $expected), ParserDiagnosticCode::BranchContext);
        }
    }

    /**
     * @param array<string, list<int>> $branchStarts
     */
    private function assertCurrentBranchHasMessages(string $blockId, array $branchStarts, int $messageCount, Line $line): void
    {
        $starts = $branchStarts[$blockId] ?? [];
        $key = array_key_last($starts);
        $start = null !== $key ? $starts[$key] : null;
        if (null === $start || $messageCount <= $start) {
            throw ParseErrors::syntax($line, 'Sequence branch must contain at least one message', ParserDiagnosticCode::EmptyBranch);
        }
    }

    /**
     * @param array<string, list<string>> $branchLabels
     * @param array<string, list<int>>    $branchStarts
     * @param array<string, list<Line>>   $branchLines
     *
     * @return list<SequenceBlockBranch>
     */
    private function branches(string $blockId, array $branchLabels, array $branchStarts, array $branchLines, int $lastMessageIndex, Line $line): array
    {
        $labels = $branchLabels[$blockId] ?? [];
        $starts = $branchStarts[$blockId] ?? [];
        $lines = $branchLines[$blockId] ?? [];
        $branches = [];
        foreach ($starts as $index => $start) {
            $end = ($starts[$index + 1] ?? ($lastMessageIndex + 1)) - 1;
            if ($end < $start) {
                throw ParseErrors::syntax($lines[$index] ?? $line, 'Sequence branch must contain at least one message', ParserDiagnosticCode::EmptyBranch);
            }
            $branches[] = new SequenceBlockBranch($labels[$index] ?? '', $start, $end);
        }

        return $branches;
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
