<?php

declare(strict_types=1);

namespace Atelier\Diagram\State;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

/**
 * Fluent builder for state diagrams.
 *
 * Transitions referencing undeclared state ids auto-declare them with the
 * id as label (Mermaid behavior). Declaring an existing state again with a
 * label overrides its label; without a label it is a no-op.
 */
final class StateDiagramBuilder
{
    private Direction $direction = Direction::TopToBottom;

    private ?Title $title = null;

    /**
     * @var array<string, State>
     */
    private array $states = [];

    /**
     * @var list<Transition>
     */
    private array $transitions = [];

    /**
     * Declares a state, or overrides the label of an existing one.
     */
    public function state(string $id, ?string $label = null): self
    {
        if (StateDiagram::INITIAL === $id || StateDiagram::FINAL === $id) {
            throw new InvalidDiagramException(\sprintf('"%s" is a reserved pseudo-state id and cannot be declared as a state.', $id));
        }

        if (!isset($this->states[$id]) || null !== $label) {
            $this->states[$id] = new State($id, $label);
        }

        return $this;
    }

    /**
     * Adds a transition, auto-declaring unknown endpoint states.
     *
     * $from may be StateDiagram::INITIAL and $to may be StateDiagram::FINAL.
     */
    public function transition(string $from, string $to, ?string $label = null): self
    {
        if (StateDiagram::FINAL === $from) {
            throw new InvalidDiagramException('A transition cannot start from the final pseudo-state.');
        }
        if (StateDiagram::INITIAL === $to) {
            throw new InvalidDiagramException('A transition cannot end at the initial pseudo-state.');
        }

        if (StateDiagram::INITIAL !== $from) {
            $this->state($from);
        }
        if (StateDiagram::FINAL !== $to) {
            $this->state($to);
        }

        $this->transitions[] = new Transition($from, $to, null !== $label ? new Label($label) : null);

        return $this;
    }

    /**
     * Adds a transition from the initial pseudo-state to $to.
     */
    public function initial(string $to): self
    {
        return $this->transition(StateDiagram::INITIAL, $to);
    }

    /**
     * Adds a transition from $from to the final pseudo-state.
     */
    public function final(string $from): self
    {
        return $this->transition($from, StateDiagram::FINAL);
    }

    public function direction(Direction $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = new Title($title);

        return $this;
    }

    public function build(): StateDiagram
    {
        if ([] === $this->states) {
            throw new InvalidDiagramException('A state diagram must declare at least one state.');
        }

        return new StateDiagram($this->direction, array_values($this->states), $this->transitions, $this->title);
    }
}
