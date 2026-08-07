<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Git\GitGraphBuilder;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\QuotedAttributeList;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;

/**
 * Minimal line-based parser for the Mermaid `gitGraph` grammar.
 *
 * Supported subset:
 *
 * - header: `gitGraph`, optionally suffixed `LR:` or `TB:`
 * - `commit`, with optional `id: "x"` and `tag: "v1"` in either order
 * - `branch <name>`, `checkout <name>` / `switch <name>`, `merge <name>`
 *
 * Anything else (cherry-pick, commit type:, options, ...) throws a
 * ParseException carrying the line number -- never a silent skip. Semantic
 * builder errors (merge of an unknown branch, duplicate commit id, ...)
 * are wrapped in a ParseException carrying the offending line, with the
 * original InvalidDiagramException as previous. The parser is a facade
 * over GitGraphBuilder and only calls its public API.
 *
 * @implements MermaidDiagramParserInterface<GitGraph>
 */
final class GitGraphParser implements MermaidDiagramParserInterface
{
    private const string HEADER = 'git.header';
    private const string COMMIT = 'git.commit';
    private const string OPERATION = 'git.operation';

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): GitGraph
    {
        $builder = new GitGraphBuilder();

        $header ??= HeaderParser::firstSignificantLine($source, $limits);
        $headerLine = $header->line;
        $this->parseHeader($headerLine, $builder);

        foreach (new LineStream($source, $limits, $header->nextOffset, $headerLine->number + 1) as $line) {
            try {
                $this->parseStatement($line, $builder);
            } catch (InvalidDiagramException $exception) {
                throw ParseErrors::wrap($line, $exception);
            }
        }

        return $builder->build();
    }

    private function parseHeader(Line $line, GitGraphBuilder $builder): void
    {
        $match = StatementMatcher::for($line)->matchRule(self::rule(self::HEADER, '/^gitGraph(?:\s+(LR|TB):)?$/'));
        if (null === $match) {
            throw ParseErrors::expectedHeader($line, '"gitGraph"');
        }

        if ('TB' === $match->optional(1)) {
            $builder->direction(Direction::TopToBottom);
        }
    }

    private function parseStatement(Line $line, GitGraphBuilder $builder): void
    {
        $matcher = new StatementMatcher($line);

        if (null !== $match = $matcher->matchRule(self::rule(self::COMMIT, '/^commit(\s+\S.*)?$/'))) {
            $this->parseCommit($line, trim($match->optional(1) ?? ''), $builder);

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::OPERATION, '/^(branch|checkout|switch|merge)\s+(\S+)$/'))) {
            match ($match->get(1)) {
                'branch' => $builder->branch($match->get(2)),
                'checkout', 'switch' => $builder->checkout($match->get(2)),
                default => $builder->merge($match->get(2)),
            };

            return;
        }

        throw ParseErrors::unsupported($line, 'gitGraph');
    }

    /**
     * Parses the attribute list of a commit statement: zero or more of
     * `id: "..."` and `tag: "..."`, each at most once, in either order.
     */
    private function parseCommit(Line $line, string $attributes, GitGraphBuilder $builder): void
    {
        $parsed = QuotedAttributeList::parse(
            $line,
            $attributes,
            ['id', 'tag'],
            'Invalid commit attribute, expected id: "..." or tag: "..."',
            'Duplicate commit attribute',
        );

        $builder->commit($parsed['id'] ?? null, $parsed['tag'] ?? null);
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
