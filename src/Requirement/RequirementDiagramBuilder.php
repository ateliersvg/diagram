<?php

declare(strict_types=1);

namespace Atelier\Diagram\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;

final class RequirementDiagramBuilder
{
    /**
     * @var array<string, array{kind: RequirementNodeKind, fields: array<string, string>}>
     */
    private array $nodes = [];

    /**
     * @var list<string>
     */
    private array $order = [];

    /**
     * @var list<RequirementRelationship>
     */
    private array $relationships = [];

    /**
     * @param array<string, string> $fields
     */
    public function requirement(string $id, array $fields = []): self
    {
        return $this->node($id, RequirementNodeKind::Requirement, $fields);
    }

    /**
     * @param array<string, string> $fields
     */
    public function element(string $id, array $fields = []): self
    {
        return $this->node($id, RequirementNodeKind::Element, $fields);
    }

    /**
     * @param array<string, string> $fields
     */
    public function node(string $id, RequirementNodeKind|string $kind, array $fields = []): self
    {
        $kind = \is_string($kind) ? RequirementNodeKind::fromKeyword($kind) : $kind;
        $this->assertIdentifier($id, 'Requirement diagram node id');

        if (isset($this->nodes[$id]) && $this->nodes[$id]['kind'] !== $kind) {
            throw new InvalidArgumentException(\sprintf('Requirement diagram node "%s" cannot be both "%s" and "%s".', $id, $this->nodes[$id]['kind']->value, $kind->value));
        }

        if (!isset($this->nodes[$id])) {
            $this->nodes[$id] = ['kind' => $kind, 'fields' => []];
            $this->order[] = $id;
        }

        foreach ($fields as $name => $value) {
            $this->field($id, (string) $name, $value);
        }

        return $this;
    }

    public function field(string $nodeId, string $name, string $value): self
    {
        if (!isset($this->nodes[$nodeId])) {
            throw new InvalidArgumentException(\sprintf('Cannot add requirement field to unknown node "%s".', $nodeId));
        }

        $this->assertFieldName($name);
        if ('' === trim($value)) {
            throw new InvalidArgumentException(\sprintf('Requirement diagram field "%s" must be non-empty.', $name));
        }

        $this->nodes[$nodeId]['fields'][$name] = trim($value);

        return $this;
    }

    public function relationship(string $from, RequirementRelationshipKind|string $kind, string $to): self
    {
        $kind = \is_string($kind) ? RequirementRelationshipKind::fromToken($kind) : $kind;
        $this->relationships[] = new RequirementRelationship($from, $to, $kind);

        return $this;
    }

    public function build(): RequirementDiagram
    {
        $nodes = [];
        foreach ($this->order as $id) {
            $node = $this->nodes[$id];
            $nodes[] = new RequirementNode($id, $node['kind'], $node['fields']);
        }

        return new RequirementDiagram($nodes, $this->relationships);
    }

    private function assertIdentifier(string $id, string $what): void
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $id)) {
            throw new InvalidArgumentException(\sprintf('%s "%s" must be a Mermaid-style identifier.', $what, $id));
        }
    }

    private function assertFieldName(string $name): void
    {
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException(\sprintf('Requirement diagram field name "%s" must be a simple identifier.', $name));
        }
    }
}
