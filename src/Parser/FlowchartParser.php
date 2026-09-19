<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Model\Direction;
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

/**
 * Parses the first supported Mermaid flowchart subset:
 *
 *     flowchart TD
 *     A[Start]
 *     A --> B
 *     B -->|label| C
 *
 * Shapes other than `[]`, nested subgraphs, link styles, class declarations
 * and markdown labels are explicitly out of scope for this version.
 *
 * @implements MermaidDiagramParserInterface<Flowchart>
 */
final class FlowchartParser implements MermaidDiagramParserInterface
{
    private const string HEADER = 'flowchart.header';
    private const string SUBGRAPH = 'flowchart.subgraph';
    private const string TITLE = 'flowchart.title';
    private const string NODE = 'flowchart.node';
    private const string EDGE = 'flowchart.edge';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): Flowchart
    {
        $builder = new FlowchartBuilder();
        $blocks = new BlockScanner();

        $header ??= HeaderParser::firstSignificantLine($source, $limits);
        $headerLine = $header->line;
        $match = StatementMatcher::for($headerLine)->matchRule(self::rule(self::HEADER, '/^flowchart\s+(TD|TB|LR)$/'));
        if (null === $match) {
            throw ParseErrors::expectedHeader($headerLine, '"flowchart TD" or "flowchart LR"');
        }
        $builder->direction('LR' === $match->get(1) ? Direction::LeftToRight : Direction::TopToBottom);

        foreach (new LineStream($source, $limits, $header->nextOffset, $headerLine->number + 1) as $line) {
            $blocks->observe($line);

            if (null !== $match = StatementMatcher::for($line)->matchRule(self::rule(self::SUBGRAPH, '/^subgraph\s+('.IdentifierPattern::STRICT.')(?:\s+\[(.+)\])?$/'))) {
                $label = ParserValues::nonEmptyLabel($line, $match->optional(2) ?? $match->get(1), 'Flow subgraph');
                $blocks->begin('subgraph', $match->get(1), $label, $line);
                continue;
            }

            if ('end' === $line->content) {
                $frame = $blocks->current();
                $span = null !== $frame ? $blocks->endExpected($line, 'flowchart', 'subgraph') : $blocks->end($line, 'flowchart');
                if ([] === $span->touchedValues) {
                    throw ParseErrors::syntax($span->startLine, 'Flow subgraph must contain at least one node', ParserDiagnosticCode::EmptyBlock);
                }
                $builder->subgraph($span->id, $span->label, $span->touchedValues, $span->parentId, $span->depth);
                continue;
            }

            try {
                foreach ($this->parseLine($builder, $line) as $nodeId) {
                    $blocks->touchAll($nodeId);
                }
            } catch (InvalidArgumentException $exception) {
                throw ParseErrors::wrap($line, $exception);
            }
        }

        $blocks->assertClosed('flowchart');

        try {
            return $builder->build();
        } catch (InvalidArgumentException $exception) {
            throw ParseErrors::wrap($headerLine, $exception);
        }
    }

    /**
     * @return list<string> node ids touched by the statement
     */
    private function parseLine(FlowchartBuilder $builder, Line $line): array
    {
        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmpty($line, $match->get(1), 'Flow title'));

            return [];
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::NODE, '/^('.$id.')\[([^\]]+)\]$/'))) {
            $builder->node($match->get(1), self::label($line, $match->get(2), 'Flow node'));

            return [$match->get(1)];
        }

        $shape = '(?:\[([^\]]+)\])?';

        if (null !== $match = $matcher->matchRule(self::rule(self::EDGE, '/^('.$id.')'.$shape.'\s*-->\s*(?:\|([^|]+)\|\s*)?('.$id.')'.$shape.'$/'))) {
            $from = $match->get(1);
            $to = $match->get(4);

            // A shape written inside the edge declares the node, exactly as a line
            // of its own would. Declaring it first means the edge finds the label.
            // A trailing optional group that does not participate has no capture
            // at all, so these are read as optional rather than by index.
            $fromShape = $match->optional(2);
            $toShape = $match->optional(5);

            if (null !== $fromShape && '' !== $fromShape) {
                $builder->node($from, self::label($line, $fromShape, 'Flow node'));
            }

            if (null !== $toShape && '' !== $toShape) {
                $builder->node($to, self::label($line, $toShape, 'Flow node'));
            }

            $rawEdgeLabel = $match->optional(3);
            $edgeLabel = null !== $rawEdgeLabel && '' !== $rawEdgeLabel ? self::unquote($rawEdgeLabel) : null;
            $builder->edge($from, $to, ParserValues::optionalNonEmptyLabel($line, $edgeLabel, 'Flow edge'));

            return [$from, $to];
        }

        throw ParseErrors::unsupported($line, 'flowchart');
    }

    private static function label(Line $line, string $raw, string $what): string
    {
        return ParserValues::nonEmptyLabel($line, self::unquote($raw), $what);
    }

    /**
     * Drops the quotes Mermaid uses to escape a label, so they stay out of the text.
     */
    private static function unquote(string $value): string
    {
        $value = trim($value);

        if (\strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
