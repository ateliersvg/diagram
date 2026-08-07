<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserDiagnosticCode;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\SourceStatementMatcher;
use Atelier\Diagram\Parser\Support\SourceStatementRule;
use Atelier\Diagram\Parser\Support\SourceStatementRules;

/**
 * @implements MermaidDiagramParserInterface<MindmapDiagram>
 */
final class MindmapDiagramParser implements MermaidDiagramParserInterface
{
    private const string ROOT = 'mindmap.root';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): MindmapDiagram
    {
        $builder = new MindmapDiagramBuilder();
        $rootSeen = false;

        /** @var array<int, string> $stack */
        $stack = [];
        $lastDepth = -1;
        $sequence = 0;

        $header ??= HeaderParser::firstSignificantLine($source, $limits);
        $headerLine = $header->line;
        if ('mindmap' !== $headerLine->content) {
            throw ParseErrors::expectedHeader($headerLine, '"mindmap"');
        }

        foreach (new SourceLineStream($source, $limits, $header->nextOffset, $headerLine->number + 1) as $sourceLine) {
            if ($sourceLine->containsTab()) {
                throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap indentation must use spaces only', ParserDiagnosticCode::IndentationTabs);
            }

            if (0 !== $sourceLine->indent % 2) {
                throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap indentation must use 2 spaces per level', ParserDiagnosticCode::IndentationStep);
            }
            if (0 === $sourceLine->indent) {
                throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap nodes must be indented under the header', ParserDiagnosticCode::IndentationExpected);
            }

            $depth = (int) ($sourceLine->indent / 2) - 1;
            if ($depth > $lastDepth + 1) {
                throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap indentation cannot skip levels', ParserDiagnosticCode::IndentationSkip);
            }

            $label = $this->labelForLine($sourceLine, $depth);
            $id = 'mindmap_node_'.++$sequence;

            try {
                if (0 === $depth) {
                    if ($rootSeen) {
                        throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap diagram must contain exactly one root', ParserDiagnosticCode::SemanticError);
                    }
                    $builder->root($label, $id);
                    $rootSeen = true;
                } else {
                    if (!isset($stack[$depth - 1])) {
                        // @codeCoverageIgnoreStart
                        // Unreachable: the stack is always contiguous 0..lastDepth (the skip-level
                        // guard above forbids gaps), so the parent at $depth - 1 always exists.
                        throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap node is missing a parent at the previous indentation level', ParserDiagnosticCode::SemanticError);
                        // @codeCoverageIgnoreEnd
                    }
                    $builder->child($stack[$depth - 1], $label, $id);
                }
            } catch (InvalidArgumentException $exception) {
                // @codeCoverageIgnoreStart
                // Unreachable defensive boundary: labelForLine guarantees a non-empty label,
                // auto-ids are unique, and the parent always exists, so the builder never throws.
                throw ParseErrors::sourceSyntax($sourceLine, rtrim($exception->getMessage(), '.'), ParserDiagnosticCode::SemanticError, $exception);
                // @codeCoverageIgnoreEnd
            }

            $stack[$depth] = $id;
            foreach (array_keys($stack) as $stackDepth) {
                if ($stackDepth > $depth) {
                    unset($stack[$stackDepth]);
                }
            }
            $lastDepth = $depth;
        }

        try {
            return $builder->build();
        } catch (InvalidArgumentException $exception) {
            throw ParseErrors::wrap($headerLine, $exception);
        }
    }

    private function labelForLine(SourceLine $sourceLine, int $depth): string
    {
        $content = $sourceLine->trimmed;
        $matcher = SourceStatementMatcher::for($sourceLine);

        if (0 === $depth && null !== ($match = $matcher->matchRule(self::rule(self::ROOT, '/^root\(\((.+)\)\)$/')))) {
            return ParserValues::nonEmptyLabel($sourceLine->asRawLine(), $match->get(1), 'Mindmap root');
        }

        if (str_contains($content, '((') || str_contains($content, '))')) {
            throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap v0 only supports root((Text)) shape syntax on the root node', ParserDiagnosticCode::UnsupportedSyntax);
        }

        if ('' === $content) {
            // @codeCoverageIgnoreStart
            // Unreachable: SourceLineStream never yields a line whose trimmed content is empty.
            throw ParseErrors::sourceSyntax($sourceLine, 'Mindmap node label must not be empty', ParserDiagnosticCode::EmptyLabel);
            // @codeCoverageIgnoreEnd
        }

        return $content;
    }

    private static function rule(string $name, string $pattern): SourceStatementRule
    {
        return SourceStatementRules::get($name, $pattern);
    }
}
