<?php

declare(strict_types=1);

namespace Atelier\Diagram\State;

use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Title;

/**
 * State diagram model: declared states and the transitions between them.
 *
 * Initial/final pseudo-states are singletons addressed by the INITIAL and
 * FINAL constants in Transition endpoints; they never appear in $states.
 * Built by StateDiagramBuilder, which owns all semantic validation.
 */
final readonly class StateDiagram implements DiagramModel
{
    /**
     * Sentinel id of the initial pseudo-state. Only valid as Transition::$from.
     */
    public const string INITIAL = '[*:initial]';

    /**
     * Sentinel id of the final pseudo-state. Only valid as Transition::$to.
     */
    public const string FINAL = '[*:final]';

    /**
     * @param list<State>      $states      declared states, in declaration order
     * @param list<Transition> $transitions transitions, in declaration order
     */
    public function __construct(
        public Direction $direction,
        public array $states,
        public array $transitions,
        public ?Title $title = null,
    ) {
    }
}
