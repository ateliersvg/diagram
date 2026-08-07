<?php

declare(strict_types=1);

namespace Atelier\Diagram\Mindmap;

use Atelier\Diagram\Exception\InvalidArgumentException;

final class MindmapDiagramBuilder
{
    private ?string $rootId = null;

    /**
     * @var array<string, string>
     */
    private array $labels = [];

    /**
     * @var array<string, list<string>>
     */
    private array $children = [];

    private int $autoIdSequence = 0;

    public function root(string $label, ?string $id = null): self
    {
        if (null !== $this->rootId) {
            throw new InvalidArgumentException('Mindmap diagram must contain exactly one root.');
        }

        $id ??= $this->nextId();
        $this->addNode($id, $label);
        $this->rootId = $id;

        return $this;
    }

    public function child(string $parentId, string $label, ?string $id = null): self
    {
        if (!isset($this->labels[$parentId])) {
            throw new InvalidArgumentException(\sprintf('Mindmap child references unknown parent "%s".', $parentId));
        }

        $id ??= $this->nextId();
        $this->addNode($id, $label);
        $this->children[$parentId][] = $id;

        return $this;
    }

    public function build(): MindmapDiagram
    {
        if (null === $this->rootId) {
            throw new InvalidArgumentException('Mindmap diagram must contain exactly one root.');
        }

        return new MindmapDiagram($this->buildNode($this->rootId));
    }

    private function addNode(string $id, string $label): void
    {
        $id = trim($id);
        $label = trim($label);

        if ('' === $id) {
            throw new InvalidArgumentException('Mindmap node id must be non-empty.');
        }
        if ('' === $label) {
            throw new InvalidArgumentException('Mindmap node label must be non-empty.');
        }
        if (isset($this->labels[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate mindmap node id "%s".', $id));
        }

        $this->labels[$id] = $label;
        $this->children[$id] = [];
    }

    private function buildNode(string $id): MindmapNode
    {
        return new MindmapNode(
            $id,
            $this->labels[$id],
            array_map(fn (string $childId): MindmapNode => $this->buildNode($childId), $this->children[$id]),
        );
    }

    private function nextId(): string
    {
        return 'mindmap_node_'.++$this->autoIdSequence;
    }
}
