<?php

declare(strict_types=1);

namespace Atelier\Diagram\Architecture;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

final class ArchitectureDiagramBuilder
{
    private ?Title $title = null;

    /**
     * @var array<string, ArchitectureGroup>
     */
    private array $groups = [];

    /**
     * @var list<string>
     */
    private array $groupOrder = [];

    /**
     * @var array<string, ArchitectureNode>
     */
    private array $nodes = [];

    /**
     * @var list<string>
     */
    private array $nodeOrder = [];

    /**
     * @var list<ArchitectureRelationship>
     */
    private array $relationships = [];

    public function title(string $text): self
    {
        $this->assertText($text, 'Architecture title');
        $this->title = new Title($text);

        return $this;
    }

    public function group(string $id, ?string $label = null): self
    {
        $this->assertIdentifier($id, 'Architecture group id');
        if (isset($this->groups[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate architecture group id "%s".', $id));
        }
        if (null !== $label) {
            $this->assertText($label, \sprintf('Architecture group "%s" label', $id));
        }

        $this->groups[$id] = new ArchitectureGroup($id, new Label($label ?? $id));
        $this->groupOrder[] = $id;

        return $this;
    }

    public function node(string|ArchitectureNodeKind $kind, string $id, ?string $label = null, ?string $groupId = null): self
    {
        $this->assertIdentifier($id, 'Architecture node id');
        if (isset($this->nodes[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate architecture node id "%s".', $id));
        }
        if (null !== $label) {
            $this->assertText($label, \sprintf('Architecture node "%s" label', $id));
        }
        if (null !== $groupId) {
            $this->assertKnownGroup($groupId);
        }

        $nodeKind = \is_string($kind) ? ArchitectureNodeKind::fromToken($kind) : $kind;
        $this->nodes[$id] = new ArchitectureNode($id, $nodeKind, new Label($label ?? $id), $groupId);
        $this->nodeOrder[] = $id;

        return $this;
    }

    public function person(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::Person, $id, $label, $groupId);
    }

    public function system(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::System, $id, $label, $groupId);
    }

    public function container(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::Container, $id, $label, $groupId);
    }

    public function component(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::Component, $id, $label, $groupId);
    }

    public function database(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::Database, $id, $label, $groupId);
    }

    public function queue(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::Queue, $id, $label, $groupId);
    }

    public function external(string $id, ?string $label = null, ?string $groupId = null): self
    {
        return $this->node(ArchitectureNodeKind::External, $id, $label, $groupId);
    }

    public function relationship(string $from, string $to, ?string $label = null): self
    {
        $this->assertKnownNode($from);
        $this->assertKnownNode($to);
        if (null !== $label) {
            $this->assertText($label, \sprintf('Architecture relationship "%s" to "%s" label', $from, $to));
        }

        $this->relationships[] = new ArchitectureRelationship(
            $from,
            $to,
            null !== $label ? new Label($label) : null,
        );

        return $this;
    }

    public function build(): ArchitectureDiagram
    {
        $groups = [];
        foreach ($this->groupOrder as $id) {
            $groups[] = $this->groups[$id];
        }

        $nodes = [];
        foreach ($this->nodeOrder as $id) {
            $nodes[] = $this->nodes[$id];
        }

        return new ArchitectureDiagram($groups, $nodes, $this->relationships, $this->title);
    }

    private function assertKnownGroup(string $id): void
    {
        if (!isset($this->groups[$id])) {
            throw new InvalidArgumentException(\sprintf('Architecture node references unknown group "%s".', $id));
        }
    }

    private function assertKnownNode(string $id): void
    {
        if (!isset($this->nodes[$id])) {
            throw new InvalidArgumentException(\sprintf('Architecture relationship references unknown node "%s".', $id));
        }
    }

    private function assertIdentifier(string $id, string $what): void
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $id)) {
            throw new InvalidArgumentException(\sprintf('%s "%s" is not a supported identifier.', $what, $id));
        }
    }

    private function assertText(string $text, string $what): void
    {
        if ('' === trim($text)) {
            throw new InvalidArgumentException(\sprintf('%s must be non-empty.', $what));
        }
    }
}
