<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\Support\ParseResult;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

/**
 * Dispatches Mermaid source to the grammar parser matching its header.
 *
 * The first significant line decides which registry entry parses the source.
 * Anything unknown (or empty input) throws a ParseException at line 1.
 * Diagram types without a Mermaid grammar (Venn) cannot be parsed.
 */
final class MermaidParser
{
    private readonly MermaidGrammarRegistry $registry;

    public function __construct(
        ?MermaidGrammarRegistry $registry = null,
        private readonly ?ParserInputLimits $limits = null,
    ) {
        $this->registry = $registry ?? MermaidGrammarRegistry::default();
    }

    public function parse(string $source, ?ParserInputLimits $limits = null): DiagramModel
    {
        $limits ??= $this->limits;

        $match = $this->registry->detect($source, $limits);

        return $match->grammar->parseWithHeader($source, $match->header, $limits);
    }

    public function tryParse(string $source, ?ParserInputLimits $limits = null): ParseResult
    {
        try {
            return ParseResult::success($this->parse($source, $limits));
        } catch (ParseException $exception) {
            return ParseResult::failure($exception);
        }
    }
}
