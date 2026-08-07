<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\Parser\Support\BlockScanner;
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

/**
 * @implements MermaidDiagramParserInterface<BlockDiagram>
 */
final class BlockDiagramParser implements MermaidDiagramParserInterface
{
    private const string TITLE = 'block.title';
    private const string BLOCK = 'block.block';
    private const string GROUP = 'block.group';
    private const string RELATIONSHIP = 'block.relationship';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): BlockDiagram
    {
        $groups = new BlockScanner();
        $runner = LineParserRunner::exact(['block'], '"block"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new BlockDiagramBuilder(),
                function (BlockDiagramBuilder $builder, Line $line) use ($groups): void {
                    $this->parseLine($builder, $line, $groups);
                },
                static fn (BlockDiagramBuilder $builder): BlockDiagram => $builder->build(),
                static function (BlockDiagramBuilder $_builder, Line $_header) use ($groups): void {
                    $frame = $groups->current();
                    if (null !== $frame) {
                        throw ParseErrors::unclosedBlock($frame->startLine, \sprintf('block group "%s"', $frame->id), $groups->lastLine());
                    }
                },
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new BlockDiagramBuilder(),
            function (BlockDiagramBuilder $builder, Line $line) use ($groups): void {
                $this->parseLine($builder, $line, $groups);
            },
            static fn (BlockDiagramBuilder $builder): BlockDiagram => $builder->build(),
            $header,
            static function (BlockDiagramBuilder $_builder, Line $_header) use ($groups): void {
                $frame = $groups->current();
                if (null !== $frame) {
                    throw ParseErrors::unclosedBlock($frame->startLine, \sprintf('block group "%s"', $frame->id), $groups->lastLine());
                }
            },
            $limits,
        );
    }

    private function parseLine(BlockDiagramBuilder $builder, Line $line, BlockScanner $groups): void
    {
        $groups->observe($line);

        $matcher = new StatementMatcher($line);
        $id = IdentifierPattern::STRICT;

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmptyLabel($line, $match->get(1), 'Block title'));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::BLOCK, '/^block\s+('.$id.')(?:\s+\[(.+)\])?$/'))) {
            $builder->block($match->get(1), ParserValues::optionalNonEmptyLabel($line, $match->optional(2), 'Block'));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::GROUP, '/^group\s+('.$id.')(?:\s+\[(.+)\])?$/'))) {
            if (null !== $groups->current()) {
                throw ParseErrors::syntax($line, 'Nested block groups are not supported yet', ParserDiagnosticCode::NestedBlock);
            }
            $label = ParserValues::optionalNonEmptyLabel($line, $match->optional(2), 'Block group');
            $builder->beginGroup($match->get(1), $label);
            $groups->begin('group', $match->get(1), $label ?? $match->get(1), $line);

            return;
        }

        if ('end' === $line->content) {
            $frame = $groups->current();
            null !== $frame ? $groups->endExpected($line, 'block diagram', 'group') : $groups->end($line, 'block diagram');
            $builder->endGroup();

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::RELATIONSHIP, '/^('.$id.')\s*->\s*('.$id.')(?:\s*:\s*(.+))?$/'))) {
            $builder->relationship($match->get(1), $match->get(2), ParserValues::optionalNonEmptyLabel($line, $match->optional(3), 'Block relationship'));

            return;
        }

        throw ParseErrors::unsupported($line, 'block diagram');
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
