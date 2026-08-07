<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\State\State;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\Transition;

/**
 * Serializes a StateDiagram to canonical Mermaid stateDiagram-v2 source.
 *
 * Canonical form: `stateDiagram-v2` header, `direction LR` only when
 * non-default, transitions in declaration order, `state "label" as id`
 * declarations only when needed -- when the label differs from the id, when
 * relying on transition auto-declaration would change the state declaration
 * order, or when a state is never referenced by a transition. The
 * `id : description` form is never emitted (normalized to `state ... as`).
 *
 * The title is not serialized: the supported grammar has no title
 * statement. A diagram that cannot be expressed in the parser subset
 * (state id outside `[A-Za-z_][A-Za-z0-9_]*`, label containing a quote or
 * newline, ...) throws an InvalidArgumentException.
 *
 * @internal used by MarkdownRenderer
 */
final class StateDiagramSerializer
{
    /**
     * Mirrors StateDiagramParser::ID.
     */
    private const string ID_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public function serialize(StateDiagram $diagram): string
    {
        $lines = ['stateDiagram-v2'];

        if (Direction::LeftToRight === $diagram->direction) {
            $lines[] = '    direction LR';
        }

        $position = [];
        foreach ($diagram->states as $index => $state) {
            $this->assertSerializableState($state);
            $position[$state->id] = $index;
        }

        /** @var array<string, true> $declared */
        $declared = [];

        foreach ($diagram->transitions as $transition) {
            $this->declareEndpoints($diagram, $transition, $position, $declared, $lines);
            $lines[] = $this->transitionLine($transition);
        }

        // States never referenced by a transition.
        $this->declareStatesBefore($diagram, \count($diagram->states), $declared, $lines);

        return implode("\n", $lines)."\n";
    }

    /**
     * Declares the transition endpoints so that re-parsing reproduces the
     * model's state declaration order: an endpoint may rely on transition
     * auto-declaration only when that declares it at its original position.
     *
     * @param array<string, int>  $position
     * @param array<string, true> $declared
     * @param list<string>        $lines
     */
    private function declareEndpoints(StateDiagram $diagram, Transition $transition, array $position, array &$declared, array &$lines): void
    {
        $endpoints = [];
        foreach ([$transition->from, $transition->to] as $endpoint) {
            if (StateDiagram::INITIAL === $endpoint || StateDiagram::FINAL === $endpoint || isset($declared[$endpoint]) || \in_array($endpoint, $endpoints, true)) {
                continue;
            }
            if (!isset($position[$endpoint])) {
                throw new InvalidArgumentException(\sprintf('Cannot render state diagram to Mermaid: transition endpoint "%s" is not a declared state.', $endpoint));
            }
            $endpoints[] = $endpoint;
        }

        if ([] === $endpoints) {
            return;
        }

        if (1 === \count($endpoints)) {
            $this->declareStatesBefore($diagram, $position[$endpoints[0]], $declared, $lines);
            $this->markOrDeclare($diagram->states[$position[$endpoints[0]]], $declared, $lines);

            return;
        }

        [$first, $second] = $endpoints;

        if ($position[$first] < $position[$second] && !$this->hasUndeclaredBetween($diagram, $position[$first], $position[$second], $declared)) {
            // The transition auto-declares $first then $second, matching
            // the model order; both may be left to auto-declaration.
            $this->declareStatesBefore($diagram, $position[$first], $declared, $lines);
            $this->markOrDeclare($diagram->states[$position[$first]], $declared, $lines);
            $this->markOrDeclare($diagram->states[$position[$second]], $declared, $lines);

            return;
        }

        // Auto-declaring both endpoints would misplace the lower-positioned
        // one: declare everything up to the highest position explicitly,
        // only the endpoint at that position may auto-declare.
        $highest = max($position[$first], $position[$second]);
        $this->declareStatesBefore($diagram, $highest, $declared, $lines);
        $this->markOrDeclare($diagram->states[$highest], $declared, $lines);
    }

    /**
     * Explicitly declares every still-undeclared state positioned before $limit.
     *
     * @param array<string, true> $declared
     * @param list<string>        $lines
     */
    private function declareStatesBefore(StateDiagram $diagram, int $limit, array &$declared, array &$lines): void
    {
        for ($index = 0; $index < $limit; ++$index) {
            $state = $diagram->states[$index];
            if (!isset($declared[$state->id])) {
                $this->declareState($state, $declared, $lines);
            }
        }
    }

    /**
     * @param array<string, true> $declared
     */
    private function hasUndeclaredBetween(StateDiagram $diagram, int $after, int $before, array $declared): bool
    {
        for ($index = $after + 1; $index < $before; ++$index) {
            if (!isset($declared[$diagram->states[$index]->id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Emits an explicit declaration when the label differs from the id,
     * otherwise leaves the state to transition auto-declaration.
     *
     * @param array<string, true> $declared
     * @param list<string>        $lines
     */
    private function markOrDeclare(State $state, array &$declared, array &$lines): void
    {
        if ($state->label !== $state->id) {
            $this->declareState($state, $declared, $lines);
        } else {
            $declared[$state->id] = true;
        }
    }

    /**
     * @param array<string, true> $declared
     * @param list<string>        $lines
     */
    private function declareState(State $state, array &$declared, array &$lines): void
    {
        $lines[] = \sprintf('    state "%s" as %s', $state->label, $state->id);
        $declared[$state->id] = true;
    }

    private function transitionLine(Transition $transition): string
    {
        $from = StateDiagram::INITIAL === $transition->from ? '[*]' : $transition->from;
        $to = StateDiagram::FINAL === $transition->to ? '[*]' : $transition->to;
        $line = \sprintf('    %s --> %s', $from, $to);

        if (null !== $transition->label) {
            $text = $transition->label->text;
            if ('' === $text || trim($text) !== $text || 1 === preg_match('/[\r\n]/', $text)) {
                throw new InvalidArgumentException(\sprintf('Cannot render state diagram to Mermaid: transition label "%s" must be a single trimmed non-empty line.', $text));
            }
            $line .= ' : '.$text;
        }

        return $line;
    }

    private function assertSerializableState(State $state): void
    {
        if (1 !== preg_match(self::ID_PATTERN, $state->id)) {
            throw new InvalidArgumentException(\sprintf('Cannot render state diagram to Mermaid: state id "%s" is not a valid Mermaid identifier.', $state->id));
        }

        if (str_contains($state->label, '"') || 1 === preg_match('/[\r\n]/', $state->label)) {
            throw new InvalidArgumentException(\sprintf('Cannot render state diagram to Mermaid: label of state "%s" must not contain quotes or newlines.', $state->id));
        }
    }
}
