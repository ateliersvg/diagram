<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Er\ErCardinality;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Parser\Support\BlockScanner;
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
 * @implements MermaidDiagramParserInterface<ErDiagram>
 */
final class ErDiagramParser implements MermaidDiagramParserInterface
{
    private const string ATTRIBUTE = 'er.attribute';
    private const string ENTITY = 'er.entity';
    private const string RELATIONSHIP = 'er.relationship';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): ErDiagram
    {
        $blocks = new BlockScanner();
        $runner = LineParserRunner::exact(['erDiagram'], '"erDiagram"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new ErDiagramBuilder(),
                function (ErDiagramBuilder $builder, Line $line) use ($blocks): void {
                    $this->parseLine($builder, $line, $blocks);
                },
                static fn (ErDiagramBuilder $builder): ErDiagram => $builder->build(),
                static function (ErDiagramBuilder $_builder, Line $_header) use ($blocks): void {
                    $frame = $blocks->current();
                    if (null !== $frame) {
                        throw ParseErrors::unclosedBlock($frame->startLine, \sprintf('ER entity "%s"', $frame->id), $blocks->lastLine());
                    }
                },
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new ErDiagramBuilder(),
            function (ErDiagramBuilder $builder, Line $line) use ($blocks): void {
                $this->parseLine($builder, $line, $blocks);
            },
            static fn (ErDiagramBuilder $builder): ErDiagram => $builder->build(),
            $header,
            static function (ErDiagramBuilder $_builder, Line $_header) use ($blocks): void {
                $frame = $blocks->current();
                if (null !== $frame) {
                    throw ParseErrors::unclosedBlock($frame->startLine, \sprintf('ER entity "%s"', $frame->id), $blocks->lastLine());
                }
            },
            $limits,
        );
    }

    private function parseLine(ErDiagramBuilder $builder, Line $line, BlockScanner $blocks): void
    {
        $blocks->observe($line);

        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;
        $cardinality = preg_quote(ErCardinality::ZeroOrOne->value, '/').'|'.preg_quote(ErCardinality::ExactlyOne->value, '/').'|'.preg_quote(ErCardinality::ZeroOrMore->value, '/').'|'.preg_quote(ErCardinality::OneOrMore->value, '/');
        $currentEntity = $blocks->current();

        if (null !== $currentEntity) {
            if ('}' === $line->content) {
                $blocks->endExpected($line, 'ER', 'entity');

                return;
            }

            if (null !== $match = $matcher->matchRule(self::rule(self::ATTRIBUTE, '/^(\S+)\s+('.$id.')$/'))) {
                $builder->attribute($currentEntity->id, $match->get(1), $match->get(2));

                return;
            }

            throw ParseErrors::unsupported($line, 'ER entity block');
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::ENTITY, '/^('.$id.')\s*\{$/'))) {
            $builder->entity($match->get(1));
            $blocks->begin('entity', $match->get(1), $match->get(1), $line);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::RELATIONSHIP, '/^('.$id.')\s+('.$cardinality.')--('.$cardinality.')\s+('.$id.')(?:\s*:\s*(.+))?$/'))) {
            $label = ParserValues::optionalNonEmptyLabel($line, $match->optional(5), 'ER relationship');
            $builder->relationship($match->get(1), $match->get(2), $match->get(4), $match->get(3), $label);

            return;
        }

        throw ParseErrors::unsupported($line, 'ER diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
