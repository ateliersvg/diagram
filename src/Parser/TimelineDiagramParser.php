<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\LineParserRunner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\SectionContentTracker;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;

/**
 * @implements MermaidDiagramParserInterface<TimelineDiagram>
 */
final class TimelineDiagramParser implements MermaidDiagramParserInterface
{
    private const string TITLE = 'timeline.title';
    private const string SECTION = 'timeline.section';
    private const string EVENT = 'timeline.event';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): TimelineDiagram
    {
        $sections = new SectionContentTracker('Timeline section "%s" must contain at least one event.');
        $runner = LineParserRunner::exact(['timeline'], '"timeline"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new TimelineDiagramBuilder(),
                function (TimelineDiagramBuilder $builder, Line $line) use ($sections): void {
                    $this->parseLine($builder, $line, $sections);
                },
                static fn (TimelineDiagramBuilder $builder): TimelineDiagram => $builder->build(),
                static function (TimelineDiagramBuilder $_builder, Line $_header) use ($sections): void {
                    $sections->assertClosed();
                },
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new TimelineDiagramBuilder(),
            function (TimelineDiagramBuilder $builder, Line $line) use ($sections): void {
                $this->parseLine($builder, $line, $sections);
            },
            static fn (TimelineDiagramBuilder $builder): TimelineDiagram => $builder->build(),
            $header,
            static function (TimelineDiagramBuilder $_builder, Line $_header) use ($sections): void {
                $sections->assertClosed();
            },
            $limits,
        );
    }

    private function parseLine(TimelineDiagramBuilder $builder, Line $line, SectionContentTracker $sections): void
    {
        $matcher = new StatementMatcher($line);

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmptyLabel($line, $match->get(1), 'Timeline title'));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::SECTION, '/^section\s+(.+)$/'))) {
            $title = ParserValues::nonEmptyLabel($line, $match->get(1), 'Timeline section');
            $sections->begin($line, $title);
            $builder->section($title);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::EVENT, '/^(.+?)\s*:\s*(.+)$/'))) {
            $builder->event(
                ParserValues::nonEmptyLabel($line, $match->get(1), 'Timeline event'),
                ParserValues::nonEmptyLabel($line, $match->get(2), 'Timeline date'),
            );
            $sections->touch();

            return;
        }

        throw ParseErrors::unsupported($line, 'timeline diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
