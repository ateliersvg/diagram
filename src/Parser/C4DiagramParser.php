<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\C4\C4DiagramBuilder;
use Atelier\Diagram\C4\C4ElementKind;
use Atelier\Diagram\C4\C4View;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\LineParserRunner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Parser\Support\ParserValues;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;

/**
 * @implements MermaidDiagramParserInterface<C4Diagram>
 */
final class C4DiagramParser implements MermaidDiagramParserInterface
{
    private const string TITLE = 'c4.title';
    private const string BOUNDARY = 'c4.boundary';
    private const string CLOSE_BOUNDARY = 'c4.close_boundary';
    private const string CALL = 'c4.call';

    private ?Line $openBoundaryLine = null;

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): C4Diagram
    {
        $this->openBoundaryLine = null;
        $runner = LineParserRunner::exact(['C4Context', 'C4Container', 'C4Component'], '"C4Context", "C4Container" or "C4Component"', '"C4Context"');
        if (null === $header) {
            return $runner->parse(
                $source,
                new C4DiagramBuilder(),
                function (C4DiagramBuilder $builder, Line $line): void {
                    $this->parseLine($builder, $line);
                },
                static fn (C4DiagramBuilder $builder): C4Diagram => $builder->build(),
                function (C4DiagramBuilder $builder, Line $header): void {
                    $this->finalize($builder, $header);
                },
                $limits,
            );
        }

        return $runner->parseWithHeader(
            $source,
            new C4DiagramBuilder(),
            function (C4DiagramBuilder $builder, Line $line): void {
                $this->parseLine($builder, $line);
            },
            static fn (C4DiagramBuilder $builder): C4Diagram => $builder->build(),
            $header,
            function (C4DiagramBuilder $builder, Line $headerLine): void {
                $this->finalize($builder, $headerLine);
            },
            $limits,
        );
    }

    private function parseLine(C4DiagramBuilder $builder, Line $line): void
    {
        $matcher = new StatementMatcher($line);

        if (null !== $match = $matcher->matchRule(self::rule(self::TITLE, '/^title\s+(.+)$/'))) {
            $builder->title(ParserValues::nonEmpty($line, $match->get(1), 'C4 title'));

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::BOUNDARY, '/^(System_Boundary|Container_Boundary|Boundary)\((.*)\)\s*\{$/'))) {
            if (null !== $this->openBoundaryLine) {
                throw ParseErrors::syntax($line, 'Nested C4 boundaries are not supported');
            }

            $args = $this->parseArguments($line, $match->get(2), 2, 2, 'C4 boundary');
            $builder->boundary($args[0], $args[1]);
            $this->openBoundaryLine = $line;

            return;
        }

        if (null !== $matcher->matchRule(self::rule(self::CLOSE_BOUNDARY, '/^\}$/'))) {
            if (null === $this->openBoundaryLine) {
                throw ParseErrors::unexpectedBlockEnd($line, 'C4 boundary');
            }

            $builder->endBoundary();
            $this->openBoundaryLine = null;

            return;
        }

        if (null !== $match = $matcher->matchRule(self::rule(self::CALL, '/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/'))) {
            $this->parseCall($builder, $line, $match->get(1), $match->get(2));

            return;
        }

        throw ParseErrors::unsupported($line, 'C4 diagram');
    }

    private function parseCall(C4DiagramBuilder $builder, Line $line, string $macro, string $source): void
    {
        if ('Rel' === $macro) {
            $args = $this->parseArguments($line, $source, 3, 4, 'C4 relationship');
            $builder->relationship($args[0], $args[1], $args[2], $args[3] ?? null);

            return;
        }

        if (!\in_array($macro, ['Person', 'Person_Ext', 'System', 'System_Ext', 'Container', 'Container_Ext', 'ContainerDb', 'Component', 'Component_Ext', 'ComponentDb'], true)) {
            throw ParseErrors::unsupported($line, 'C4 diagram');
        }

        $kind = C4ElementKind::fromMacro($macro);
        $args = match ($kind) {
            C4ElementKind::Person, C4ElementKind::PersonExternal, C4ElementKind::System, C4ElementKind::SystemExternal => $this->parseArguments($line, $source, 2, 3, 'C4 element'),
            default => $this->parseArguments($line, $source, 2, 4, 'C4 element'),
        };

        if (C4ElementKind::Person === $kind || C4ElementKind::PersonExternal === $kind || C4ElementKind::System === $kind || C4ElementKind::SystemExternal === $kind) {
            $builder->element($kind, $args[0], $args[1], null, $args[2] ?? null);

            return;
        }

        $builder->element($kind, $args[0], $args[1], $args[2] ?? null, $args[3] ?? null);
    }

    /**
     * @return list<string>
     */
    private function parseArguments(Line $line, string $source, int $min, int $max, string $what): array
    {
        $args = [];
        $offset = 0;
        $length = \strlen($source);

        while ($offset < $length) {
            $offset = $this->skipWhitespace($source, $offset, $length);
            if ($offset >= $length) {
                break;
            }

            if ('"' === $source[$offset]) {
                ++$offset;
                $start = $offset;
                while ($offset < $length && '"' !== $source[$offset]) {
                    ++$offset;
                }
                if ($offset >= $length) {
                    throw ParseErrors::syntax($line, \sprintf('Invalid %s argument list', $what));
                }
                $args[] = substr($source, $start, $offset - $start);
                ++$offset;
            } else {
                $start = $offset;
                while ($offset < $length && ',' !== $source[$offset]) {
                    ++$offset;
                }
                $args[] = trim(substr($source, $start, $offset - $start));
            }

            $offset = $this->skipWhitespace($source, $offset, $length);
            if ($offset >= $length) {
                break;
            }
            if (',' !== $source[$offset]) {
                throw ParseErrors::syntax($line, \sprintf('Invalid %s argument list', $what));
            }
            ++$offset;
        }

        if (\count($args) < $min || \count($args) > $max || [] !== array_filter($args, static fn (string $arg): bool => '' === trim($arg))) {
            throw ParseErrors::syntax($line, \sprintf('Invalid %s argument count', $what));
        }

        return $args;
    }

    private function skipWhitespace(string $source, int $offset, int $length): int
    {
        while ($offset < $length && (' ' === $source[$offset] || "\t" === $source[$offset])) {
            ++$offset;
        }

        return $offset;
    }

    private function finalize(C4DiagramBuilder $builder, Line $header): void
    {
        $builder->view(C4View::from($header->content));
        if (null !== $this->openBoundaryLine) {
            throw ParseErrors::unclosedBlock($this->openBoundaryLine, 'C4 boundary', $this->openBoundaryLine);
        }
    }

    private static function rule(string $name, string $pattern): StatementRule
    {
        return StatementRules::get($name, $pattern);
    }
}
