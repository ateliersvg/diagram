<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
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
 * @implements MermaidDiagramParserInterface<KanbanDiagram>
 */
final class KanbanDiagramParser implements MermaidDiagramParserInterface
{
    private const string ID = '[A-Za-z_][A-Za-z0-9_-]*';
    private const string TITLE = 'kanban.title';
    private const string COLUMN = 'kanban.column';
    private const string CARD = 'kanban.card';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): KanbanDiagram
    {
        $builder = new KanbanDiagramBuilder();
        $currentColumnId = null;

        $header ??= HeaderParser::firstSignificantLine($source, $limits);
        $headerLine = $header->line;
        if ('kanban' !== $headerLine->content) {
            throw ParseErrors::expectedHeader($headerLine, '"kanban"');
        }

        foreach (new SourceLineStream($source, $limits, $header->nextOffset, $headerLine->number + 1) as $sourceLine) {
            if ($sourceLine->containsTab()) {
                throw ParseErrors::sourceSyntax($sourceLine, 'Kanban indentation must use spaces only', ParserDiagnosticCode::IndentationTabs);
            }

            try {
                $matcher = SourceStatementMatcher::for($sourceLine);

                if (4 === $sourceLine->indent && null !== ($match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/')))) {
                    $builder->title(ParserValues::nonEmpty($sourceLine->asRawLine(), $match->get(1), 'Kanban title'));

                    continue;
                }

                if (4 === $sourceLine->indent && null !== ($match = $matcher->matchRule(self::rule(self::COLUMN, '/^('.self::ID.')\s+\[([^\]]+)\]$/')))) {
                    $currentColumnId = $match->get(1);
                    $builder->column($currentColumnId, ParserValues::nonEmpty($sourceLine->asRawLine(), $match->get(2), 'Kanban column label'));

                    continue;
                }

                if (8 === $sourceLine->indent && null !== ($match = $matcher->matchRule(self::rule(self::CARD, '/^('.self::ID.')\s+\[([^\]]+)\]$/')))) {
                    if (null === $currentColumnId) {
                        throw ParseErrors::sourceSyntax($sourceLine, 'Kanban card must belong to a column', ParserDiagnosticCode::SemanticError);
                    }
                    $builder->card($currentColumnId, $match->get(1), ParserValues::nonEmpty($sourceLine->asRawLine(), $match->get(2), 'Kanban card label'));

                    continue;
                }
            } catch (InvalidArgumentException $exception) {
                throw ParseErrors::sourceSyntax($sourceLine, rtrim($exception->getMessage(), '.'), ParserDiagnosticCode::SemanticError, $exception);
            }

            throw ParseErrors::sourceSyntax($sourceLine, 'Unsupported kanban diagram syntax', ParserDiagnosticCode::UnsupportedSyntax);
        }

        try {
            return $builder->build();
        } catch (InvalidArgumentException $exception) {
            throw ParseErrors::wrap($headerLine, $exception);
        }
    }

    private static function rule(string $name, string $pattern): SourceStatementRule
    {
        return SourceStatementRules::get($name, $pattern);
    }
}
