<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Architecture\ArchitectureDiagramBuilder;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\IdentifierPattern;
use Atelier\Diagram\Parser\Support\LineParserRunner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;

/**
 * @implements MermaidDiagramParserInterface<ArchitectureDiagram>
 */
final class ArchitectureDiagramParser implements MermaidDiagramParserInterface
{
    private const string TITLE = 'architecture.title';
    private const string GROUP = 'architecture.group';
    private const string NODE = 'architecture.node';
    private const string RELATIONSHIP = 'architecture.relationship';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): ArchitectureDiagram
    {
        $currentGroup = null;
        $runner = LineParserRunner::exact(['architecture'], '"architecture"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new ArchitectureDiagramBuilder(),
                function (ArchitectureDiagramBuilder $builder, Line $line) use (&$currentGroup): void {
                    $currentGroup = $this->parseLine($builder, $line, $currentGroup);
                },
                static fn (ArchitectureDiagramBuilder $builder): ArchitectureDiagram => $builder->build(),
                null,
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new ArchitectureDiagramBuilder(),
            function (ArchitectureDiagramBuilder $builder, Line $line) use (&$currentGroup): void {
                $currentGroup = $this->parseLine($builder, $line, $currentGroup);
            },
            static fn (ArchitectureDiagramBuilder $builder): ArchitectureDiagram => $builder->build(),
            $header,
            null,
            $limits,
        );
    }

    private function parseLine(ArchitectureDiagramBuilder $builder, Line $line, ?string $currentGroup): ?string
    {
        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;
        $kind = 'person|system|container|component|database|queue|external';

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmpty($line, $match->get(1), 'Architecture title'));

            return $currentGroup;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::GROUP, '/^group\s+('.$id.')(?:\s+\[([^\]]*)\])?$/'))) {
            $builder->group($match->get(1), null !== $match->optional(2) ? ParserValues::nonEmptyLabel($line, $match->get(2), 'Architecture group') : null);

            return $match->get(1);
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::NODE, '/^('.$kind.')\s+('.$id.')(?:\s+\[([^\]]*)\])?$/'))) {
            $builder->node($match->get(1), $match->get(2), null !== $match->optional(3) ? ParserValues::nonEmptyLabel($line, $match->get(3), 'Architecture node') : null, $currentGroup);

            return $currentGroup;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::RELATIONSHIP, '/^('.$id.')\s*->\s*('.$id.')(?:\s*:\s*(.*))?$/'))) {
            $builder->relationship($match->get(1), $match->get(2), null !== $match->optional(3) ? ParserValues::nonEmptyLabel($line, $match->get(3), 'Architecture relationship') : null);

            return $currentGroup;
        }

        throw ParseErrors::unsupported($line, 'architecture diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
