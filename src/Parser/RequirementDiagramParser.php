<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

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
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementDiagramBuilder;
use Atelier\Diagram\Requirement\RequirementNodeKind;
use Atelier\Diagram\Requirement\RequirementRelationshipKind;

/**
 * @implements MermaidDiagramParserInterface<RequirementDiagram>
 */
final class RequirementDiagramParser implements MermaidDiagramParserInterface
{
    private const string FIELD = 'requirement.field';
    private const string NODE = 'requirement.node';
    private const string RELATIONSHIP = 'requirement.relationship';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): RequirementDiagram
    {
        $blocks = new BlockScanner();
        $runner = LineParserRunner::exact(['requirementDiagram'], '"requirementDiagram"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new RequirementDiagramBuilder(),
                function (RequirementDiagramBuilder $builder, Line $line) use ($blocks): void {
                    $this->parseLine($builder, $line, $blocks);
                },
                static fn (RequirementDiagramBuilder $builder): RequirementDiagram => $builder->build(),
                static function (RequirementDiagramBuilder $_builder, Line $_header) use ($blocks): void {
                    $frame = $blocks->current();
                    if (null !== $frame) {
                        throw ParseErrors::unclosedBlock($frame->startLine, \sprintf('requirement diagram node "%s"', $frame->id), $blocks->lastLine());
                    }
                },
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new RequirementDiagramBuilder(),
            function (RequirementDiagramBuilder $builder, Line $line) use ($blocks): void {
                $this->parseLine($builder, $line, $blocks);
            },
            static fn (RequirementDiagramBuilder $builder): RequirementDiagram => $builder->build(),
            $header,
            static function (RequirementDiagramBuilder $_builder, Line $_header) use ($blocks): void {
                $frame = $blocks->current();
                if (null !== $frame) {
                    throw ParseErrors::unclosedBlock($frame->startLine, \sprintf('requirement diagram node "%s"', $frame->id), $blocks->lastLine());
                }
            },
            $limits,
        );
    }

    private function parseLine(RequirementDiagramBuilder $builder, Line $line, BlockScanner $blocks): void
    {
        $blocks->observe($line);

        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;
        $currentNode = $blocks->current();

        if (null !== $currentNode) {
            if ('}' === $line->content) {
                $blocks->endExpected($line, 'requirement diagram', 'node');

                return;
            }

            if (null !== $match = $matcher->matchRule(self::rule(self::FIELD, '/^([A-Za-z][A-Za-z0-9_]*)\s*:\s*(.+)$/'))) {
                $builder->field($currentNode->id, $match->get(1), ParserValues::nonEmptyLabel($line, $match->get(2), 'Requirement field'));

                return;
            }

            throw ParseErrors::unsupported($line, 'requirement diagram node block');
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::NODE, '/^(requirement|element)\s+('.$id.')\s*\{$/'))) {
            $builder->node($match->get(2), RequirementNodeKind::fromKeyword($match->get(1)));
            $blocks->begin('node', $match->get(2), $match->get(2), $line);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::RELATIONSHIP, '/^('.$id.')\s+-\s+('.RequirementRelationshipKind::pattern().')\s+->\s+('.$id.')$/'))) {
            $builder->relationship($match->get(1), RequirementRelationshipKind::fromToken($match->get(2)), $match->get(3));

            return;
        }

        throw ParseErrors::unsupported($line, 'requirement diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
