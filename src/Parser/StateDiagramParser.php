<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\IdentifierPattern;
use Atelier\Diagram\Parser\Support\LineParserRunner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserDiagnosticCode;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\StateDiagramBuilder;

/**
 * Parses the supported Mermaid state diagram subset:
 *
 *     stateDiagram-v2          (or stateDiagram)
 *     direction TB|LR
 *     [*] --> A
 *     A --> [*]
 *     A --> B : label
 *     state "Long label" as A
 *     A : description
 *
 * Anything else (composite states, forks, notes, concurrency) throws a
 * ParseException carrying the offending line. Semantic builder errors are
 * wrapped in a ParseException carrying the offending line, with the
 * original InvalidDiagramException as previous. A facade over the public
 * StateDiagramBuilder API only.
 *
 * @implements MermaidDiagramParserInterface<StateDiagram>
 */
final class StateDiagramParser implements MermaidDiagramParserInterface
{
    private const string DIRECTION = 'state.direction';
    private const string TRANSITION = 'state.transition';
    private const string ALIAS = 'state.alias';
    private const string DESCRIPTION = 'state.description';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): StateDiagram
    {
        $runner = LineParserRunner::exact(['stateDiagram-v2', 'stateDiagram'], '"stateDiagram-v2" or "stateDiagram"', '"stateDiagram-v2"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new StateDiagramBuilder(),
                function (StateDiagramBuilder $builder, Line $line): void {
                    $this->parseLine($builder, $line);
                },
                static fn (StateDiagramBuilder $builder): StateDiagram => $builder->build(),
                null,
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new StateDiagramBuilder(),
            function (StateDiagramBuilder $builder, Line $line): void {
                $this->parseLine($builder, $line);
            },
            static fn (StateDiagramBuilder $builder): StateDiagram => $builder->build(),
            $header,
            null,
            $limits,
        );
    }

    private function parseLine(StateDiagramBuilder $builder, Line $line): void
    {
        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;

        // direction TB|LR
        if (null !== $match = $matcher->matchRule(self::rule(self::DIRECTION, '/^direction\s+(\S+)$/'))) {
            $builder->direction(match ($match->get(1)) {
                'TB' => Direction::TopToBottom,
                'LR' => Direction::LeftToRight,
                default => throw ParseErrors::syntax($line, \sprintf('Unsupported direction "%s", expected TB or LR', $match->get(1)), ParserDiagnosticCode::InvalidEnum),
            });

            return;
        }

        // [*] --> A, A --> [*], A --> B, optional ": label"
        if (null !== $match = $matcher->matchRule(self::rule(self::TRANSITION, '/^(\[\*\]|'.$id.')\s*-->\s*(\[\*\]|'.$id.')(?:\s*:\s*(.*))?$/'))) {
            $from = '[*]' === $match->get(1) ? StateDiagram::INITIAL : $match->get(1);
            $to = '[*]' === $match->get(2) ? StateDiagram::FINAL : $match->get(2);
            $label = ParserValues::optionalNonEmptyLabel($line, $match->optional(3), 'Transition');
            $builder->transition($from, $to, $label);

            return;
        }

        // state "Long label" as A
        if (null !== $match = $matcher->matchRule(self::rule(self::ALIAS, '/^state\s+"([^"]*)"\s+as\s+('.$id.')$/'))) {
            $builder->state($match->get(2), ParserValues::nonEmptyLabel($line, $match->get(1), 'State'));

            return;
        }

        // A : description
        if (null !== $match = $matcher->matchRule(self::rule(self::DESCRIPTION, '/^('.$id.')\s*:\s*(.+)$/'))) {
            $builder->state($match->get(1), ParserValues::nonEmptyLabel($line, $match->get(2), 'State'));

            return;
        }

        throw ParseErrors::unsupported($line, 'state diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
