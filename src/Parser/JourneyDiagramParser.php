<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneyDiagramBuilder;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\LineParserRunner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\SectionContentTracker;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;

/**
 * @implements MermaidDiagramParserInterface<JourneyDiagram>
 */
final class JourneyDiagramParser implements MermaidDiagramParserInterface
{
    private const string TITLE = 'journey.title';
    private const string SECTION = 'journey.section';
    private const string TASK = 'journey.task';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): JourneyDiagram
    {
        $sections = new SectionContentTracker('Journey section "%s" must contain at least one task.');
        $runner = LineParserRunner::exact(['journey'], '"journey"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new JourneyDiagramBuilder(),
                function (JourneyDiagramBuilder $builder, Line $line) use ($sections): void {
                    $this->parseLine($builder, $line, $sections);
                },
                static fn (JourneyDiagramBuilder $builder): JourneyDiagram => $builder->build(),
                static function (JourneyDiagramBuilder $_builder, Line $_header) use ($sections): void {
                    $sections->assertClosed();
                },
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new JourneyDiagramBuilder(),
            function (JourneyDiagramBuilder $builder, Line $line) use ($sections): void {
                $this->parseLine($builder, $line, $sections);
            },
            static fn (JourneyDiagramBuilder $builder): JourneyDiagram => $builder->build(),
            $header,
            static function (JourneyDiagramBuilder $_builder, Line $_header) use ($sections): void {
                $sections->assertClosed();
            },
            $limits,
        );
    }

    private function parseLine(JourneyDiagramBuilder $builder, Line $line, SectionContentTracker $sections): void
    {
        $matcher = new StatementMatcher($line);

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmptyLabel($line, $match->get(1), 'Journey title'));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::SECTION, '/^section\s+(.+)$/'))) {
            $title = ParserValues::nonEmptyLabel($line, $match->get(1), 'Journey section');
            $sections->begin($line, $title);
            $builder->section($title);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::TASK, '/^(.+?)\s*:\s*([0-9]+)\s*:\s*(.+)$/'))) {
            $builder->task(
                ParserValues::nonEmptyLabel($line, $match->get(1), 'Journey task'),
                (int) $match->get(2),
                ParserValues::commaSeparated($line, $match->get(3), 'Journey actors'),
            );
            $sections->touch();

            return;
        }

        throw ParseErrors::unsupported($line, 'journey diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
