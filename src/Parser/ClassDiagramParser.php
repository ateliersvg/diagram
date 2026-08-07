<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
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
 * @implements MermaidDiagramParserInterface<ClassDiagram>
 */
final class ClassDiagramParser implements MermaidDiagramParserInterface
{
    private const string CLASS_DECLARATION = 'class.declaration';
    private const string MEMBER = 'class.member';
    private const string RELATION = 'class.relation';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): ClassDiagram
    {
        $runner = LineParserRunner::exact(['classDiagram'], '"classDiagram"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new ClassDiagramBuilder(),
                function (ClassDiagramBuilder $builder, Line $line): void {
                    $this->parseLine($builder, $line);
                },
                static fn (ClassDiagramBuilder $builder): ClassDiagram => $builder->build(),
                null,
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new ClassDiagramBuilder(),
            function (ClassDiagramBuilder $builder, Line $line): void {
                $this->parseLine($builder, $line);
            },
            static fn (ClassDiagramBuilder $builder): ClassDiagram => $builder->build(),
            $header,
            null,
            $limits,
        );
    }

    private function parseLine(ClassDiagramBuilder $builder, Line $line): void
    {
        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;

        if (null !== $match = $matcher->matchRule(self::rule(self::CLASS_DECLARATION, '/^class\s+('.$id.')$/'))) {
            $builder->class($match->get(1));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::MEMBER, '/^('.$id.')\s*:\s*(.*)$/'))) {
            $member = ParserValues::nonEmptyLabel($line, $match->get(2), 'Class member');
            $builder->member($match->get(1), $member);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::RELATION, '/^('.$id.')\s+-->\s+('.$id.')(?:\s*:\s*(.*))?$/'))) {
            $label = ParserValues::optionalNonEmptyLabel($line, $match->optional(3), 'Class relation');
            $builder->relation($match->get(1), $match->get(2), $label);

            return;
        }

        throw ParseErrors::unsupported($line, 'class diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
