<?php

declare(strict_types=1);

namespace Atelier\Diagram\Er;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final class ErDiagramBuilder
{
    /**
     * @var array<string, list<ErAttribute>>
     */
    private array $attributes = [];

    /**
     * @var list<string>
     */
    private array $order = [];

    /**
     * @var list<ErRelationship>
     */
    private array $relationships = [];

    public function entity(string $id): self
    {
        $this->ensureEntity($id);

        return $this;
    }

    public function attribute(string $entityId, string $type, string $name): self
    {
        $this->ensureEntity($entityId);
        $this->attributes[$entityId][] = new ErAttribute($type, $name);

        return $this;
    }

    public function relationship(string $from, string|ErCardinality $fromCardinality, string $to, string|ErCardinality $toCardinality, ?string $label = null): self
    {
        $this->ensureEntity($from);
        $this->ensureEntity($to);
        $this->relationships[] = new ErRelationship(
            $from,
            \is_string($fromCardinality) ? ErCardinality::fromToken($fromCardinality) : $fromCardinality,
            $to,
            \is_string($toCardinality) ? ErCardinality::fromToken($toCardinality) : $toCardinality,
            null !== $label ? new Label($label) : null,
        );

        return $this;
    }

    public function build(): ErDiagram
    {
        $entities = [];
        foreach ($this->order as $id) {
            $entities[] = new ErEntity($id, $this->attributes[$id] ?? []);
        }

        return new ErDiagram($entities, $this->relationships);
    }

    private function ensureEntity(string $id): void
    {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('ER entity id must be non-empty.');
        }

        if (!isset($this->attributes[$id])) {
            $this->attributes[$id] = [];
            $this->order[] = $id;
        }
    }
}
