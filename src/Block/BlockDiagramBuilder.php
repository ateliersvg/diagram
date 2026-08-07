<?php

declare(strict_types=1);

namespace Atelier\Diagram\Block;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

final class BlockDiagramBuilder
{
    private ?Title $title = null;

    /**
     * @var array<string, BlockNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, array{label: string, nodeIds: list<string>}>
     */
    private array $groups = [];

    /**
     * @var list<BlockRelationship>
     */
    private array $relationships = [];

    private ?string $currentGroupId = null;

    public function title(string $text): self
    {
        $this->title = new Title($text);

        return $this;
    }

    public function block(string $id, ?string $label = null): self
    {
        $this->nodes[$id] = new BlockNode($id, $label ?? $id, $this->currentGroupId);

        if (null !== $this->currentGroupId) {
            $this->groups[$this->currentGroupId]['nodeIds'][] = $id;
        }

        return $this;
    }

    public function beginGroup(string $id, ?string $label = null): self
    {
        if (null !== $this->currentGroupId) {
            throw new InvalidArgumentException('Nested block groups are not supported yet.');
        }
        if (isset($this->groups[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate block group id "%s".', $id));
        }

        $this->groups[$id] = ['label' => $label ?? $id, 'nodeIds' => []];
        $this->currentGroupId = $id;

        return $this;
    }

    public function endGroup(): self
    {
        if (null === $this->currentGroupId) {
            throw new InvalidArgumentException('Cannot end a block group before starting one.');
        }

        $this->currentGroupId = null;

        return $this;
    }

    public function relationship(string $from, string $to, ?string $label = null): self
    {
        if (!isset($this->nodes[$from])) {
            $this->block($from);
        }
        if (!isset($this->nodes[$to])) {
            $this->block($to);
        }

        $this->relationships[] = new BlockRelationship($from, $to, null !== $label ? new Label($label) : null);

        return $this;
    }

    public function build(): BlockDiagram
    {
        if (null !== $this->currentGroupId) {
            throw new InvalidArgumentException(\sprintf('Block group "%s" is not closed.', $this->currentGroupId));
        }
        if ([] === $this->nodes) {
            throw new InvalidArgumentException('Block diagram must contain at least one block.');
        }

        $groups = [];
        foreach ($this->groups as $id => $group) {
            $groups[] = new BlockGroup($id, $group['label'], $this->nonEmptyNodeIds($id, $group['nodeIds']));
        }

        return new BlockDiagram($this->nonEmptyNodes(), $groups, $this->relationships, $this->title);
    }

    /**
     * @return non-empty-list<BlockNode>
     */
    private function nonEmptyNodes(): array
    {
        $nodes = array_values($this->nodes);
        if ([] === $nodes) {
            throw new InvalidArgumentException('Block diagram must contain at least one block.');
        }

        return $nodes;
    }

    /**
     * @param list<string> $nodeIds
     *
     * @return non-empty-list<string>
     */
    private function nonEmptyNodeIds(string $groupId, array $nodeIds): array
    {
        if ([] === $nodeIds) {
            throw new InvalidArgumentException(\sprintf('Block group "%s" must contain at least one block.', $groupId));
        }

        return array_values(array_unique($nodeIds));
    }
}
