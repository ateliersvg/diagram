<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

/**
 * Internal dispatch entry for one Mermaid-like grammar.
 *
 * @internal
 */
final readonly class MermaidGrammar
{
    /**
     * @param non-empty-list<string>                      $headers
     * @param MermaidDiagramParserInterface<DiagramModel> $parser
     */
    public function __construct(
        public string $name,
        public array $headers,
        private MermaidDiagramParserInterface $parser,
    ) {
    }

    /**
     * @throws ParseException
     */
    public function parse(string $source, ?ParserInputLimits $limits = null): DiagramModel
    {
        return $this->parser->parse($source, $limits);
    }

    /**
     * @throws ParseException
     */
    public function parseWithHeader(string $source, HeaderMatch $header, ?ParserInputLimits $limits = null): DiagramModel
    {
        return $this->parser->parse($source, $limits, $header);
    }
}
