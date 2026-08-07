<?php

declare(strict_types=1);

namespace Atelier\Diagram\ClassDiagram;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;

final class ClassDiagramBuilder
{
    /**
     * @var array<string, list<ClassMember>>
     */
    private array $members = [];

    /**
     * @var list<string>
     */
    private array $order = [];

    /**
     * @var list<ClassRelation>
     */
    private array $relations = [];

    public function class(string $id): self
    {
        $this->ensureClass($id);

        return $this;
    }

    public function member(string $classId, string $text): self
    {
        $this->ensureClass($classId);
        $this->members[$classId][] = new ClassMember($text);

        return $this;
    }

    public function relation(string $from, string $to, ?string $label = null): self
    {
        $this->ensureClass($from);
        $this->ensureClass($to);
        $this->relations[] = new ClassRelation($from, $to, null !== $label ? new Label($label) : null);

        return $this;
    }

    public function build(): ClassDiagram
    {
        $classes = [];
        foreach ($this->order as $id) {
            $classes[] = new ClassBox($id, $this->members[$id] ?? []);
        }

        return new ClassDiagram($classes, $this->relations);
    }

    private function ensureClass(string $id): void
    {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Class id must be non-empty.');
        }

        if (!isset($this->members[$id])) {
            $this->members[$id] = [];
            $this->order[] = $id;
        }
    }
}
