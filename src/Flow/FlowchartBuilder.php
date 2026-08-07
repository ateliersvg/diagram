<?php

declare(strict_types=1);

namespace Atelier\Diagram\Flow;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

/**
 * Fluent builder for the v0 flowchart subset.
 */
final class FlowchartBuilder
{
    private Direction $direction = Direction::TopToBottom;

    private ?Title $title = null;

    /**
     * @var array<string, FlowNode>
     */
    private array $nodes = [];

    /**
     * @var list<FlowEdge>
     */
    private array $edges = [];

    /**
     * @var list<FlowSubgraph>
     */
    private array $subgraphs = [];

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

    public function node(string $id, ?string $label = null): self
    {
        $this->nodes[$id] = new FlowNode($id, $label ?? $id);

        return $this;
    }

    public function edge(string $from, string $to, ?string $label = null): self
    {
        $this->node($from, $this->nodes[$from]->label ?? null);
        $this->node($to, $this->nodes[$to]->label ?? null);
        $this->edges[] = new FlowEdge($from, $to, null !== $label ? new Label($label) : null);

        return $this;
    }

    /**
     * @param non-empty-list<string> $nodeIds
     */
    public function subgraph(string $id, string $label, array $nodeIds, ?string $parentId = null, int $depth = 0): self
    {
        $this->subgraphs[] = new FlowSubgraph($id, $label, $nodeIds, $parentId, $depth);

        return $this;
    }

    public function build(): Flowchart
    {
        if ([] === $this->nodes) {
            throw new InvalidArgumentException('Flowchart must contain at least one node.');
        }

        return new Flowchart($this->direction, array_values($this->nodes), $this->edges, $this->title, $this->subgraphs);
    }
}
