<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

/**
 * Registry of supported Mermaid-like grammars.
 *
 * Keeps header dispatch data in one place so adding a textual diagram type no
 * longer grows MermaidParser's control flow.
 *
 * @internal
 */
final readonly class MermaidGrammarRegistry
{
    /**
     * @var non-empty-list<MermaidGrammar>
     */
    private array $grammars;

    /**
     * @var array<string, MermaidGrammar>
     */
    private array $grammarByHeader;

    /**
     * @var non-empty-list<string>
     */
    private array $headers;

    private string $expectedHeaders;

    /**
     * @param non-empty-list<MermaidGrammar> $grammars
     */
    public function __construct(array $grammars)
    {
        $this->grammars = $grammars;

        $grammarByHeader = [];
        $headers = [];

        foreach ($grammars as $grammar) {
            foreach ($grammar->headers as $header) {
                if (isset($grammarByHeader[$header])) {
                    throw new InvalidArgumentException(\sprintf('Duplicate Mermaid header "%s" in registry.', $header));
                }

                $grammarByHeader[$header] = $grammar;
                $headers[] = $header;
            }
        }

        $quoted = array_map(static fn (string $header): string => '"'.$header.'"', $headers);
        if (1 === \count($quoted)) {
            $expectedHeaders = $quoted[0];
        } else {
            $last = array_pop($quoted);
            $expectedHeaders = implode(', ', $quoted).' or '.$last;
        }

        $this->grammarByHeader = $grammarByHeader;
        $this->headers = $headers;
        $this->expectedHeaders = $expectedHeaders;
    }

    public static function default(): self
    {
        /** @var self|null $default */
        static $default = null;

        return $default ??= new self([
            new MermaidGrammar('state', ['stateDiagram-v2', 'stateDiagram'], new StateDiagramParser()),
            new MermaidGrammar('git', ['gitGraph'], new GitGraphParser()),
            new MermaidGrammar('sequence', ['sequenceDiagram'], new SequenceDiagramParser()),
            new MermaidGrammar('flowchart', ['flowchart'], new FlowchartParser()),
            new MermaidGrammar('class', ['classDiagram'], new ClassDiagramParser()),
            new MermaidGrammar('er', ['erDiagram'], new ErDiagramParser()),
            new MermaidGrammar('timeline', ['timeline'], new TimelineDiagramParser()),
            new MermaidGrammar('journey', ['journey'], new JourneyDiagramParser()),
            new MermaidGrammar('mindmap', ['mindmap'], new MindmapDiagramParser()),
            new MermaidGrammar('requirement', ['requirementDiagram'], new RequirementDiagramParser()),
            new MermaidGrammar('kanban', ['kanban'], new KanbanDiagramParser()),
            new MermaidGrammar('block', ['block'], new BlockDiagramParser()),
            new MermaidGrammar('architecture', ['architecture'], new ArchitectureDiagramParser()),
            new MermaidGrammar('c4', ['C4Context', 'C4Container', 'C4Component'], new C4DiagramParser()),
        ]);
    }

    /**
     * @return non-empty-list<MermaidGrammar>
     */
    public function grammars(): array
    {
        return $this->grammars;
    }

    /**
     * @return non-empty-list<string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function expectedHeaders(): string
    {
        return $this->expectedHeaders;
    }

    /**
     * @throws ParseException
     */
    public function detect(string $source, ?ParserInputLimits $limits = null): MermaidGrammarMatch
    {
        $header = HeaderParser::firstSignificantLine($source, $limits);
        $line = $header->line;
        $keyword = HeaderParser::keyword($line->content);

        if (isset($this->grammarByHeader[$keyword])) {
            return new MermaidGrammarMatch(
                $this->grammarByHeader[$keyword],
                $header,
            );
        }

        throw ParseErrors::unknownHeader($line, $this->expectedHeaders());
    }
}
