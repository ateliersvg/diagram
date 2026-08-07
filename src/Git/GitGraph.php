<?php

declare(strict_types=1);

namespace Atelier\Diagram\Git;

use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Title;

/**
 * Immutable git graph model: branches in creation order, commits in
 * operation order.
 *
 * Built through GitGraphBuilder (or GitGraphParser for Mermaid text);
 * laid out by Layout\Git\GitLayoutEngine.
 */
final readonly class GitGraph implements DiagramModel
{
    /**
     * @param Direction    $direction  flow direction of the commit axis
     * @param list<Branch> $branches   branches in creation order -- lane order for layout
     * @param list<Commit> $commits    commits in operation order -- axis order for layout
     * @param Title|null   $title      optional diagram title
     * @param bool         $showLegend whether layout adds the automatic branch -> color legend
     */
    public function __construct(
        public Direction $direction = Direction::LeftToRight,
        public array $branches = [],
        public array $commits = [],
        public ?Title $title = null,
        public bool $showLegend = true,
    ) {
    }
}
