<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

final class C4DiagramBuilder
{
    private C4View $view = C4View::Context;

    private ?Title $title = null;

    /**
     * @var array<string, C4Boundary>
     */
    private array $boundaries = [];

    /**
     * @var list<string>
     */
    private array $boundaryOrder = [];

    /**
     * @var array<string, C4Element>
     */
    private array $elements = [];

    /**
     * @var list<string>
     */
    private array $elementOrder = [];

    /**
     * @var list<C4Relationship>
     */
    private array $relationships = [];

    private ?string $currentBoundary = null;

    public function view(C4View $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function contextView(): self
    {
        return $this->view(C4View::Context);
    }

    public function containerView(): self
    {
        return $this->view(C4View::Container);
    }

    public function componentView(): self
    {
        return $this->view(C4View::Component);
    }

    public function title(string $text): self
    {
        $this->assertText($text, 'C4 title');
        $this->title = new Title($text);

        return $this;
    }

    public function boundary(string $id, ?string $label = null): self
    {
        $this->assertIdentifier($id, 'C4 boundary id');
        if (isset($this->boundaries[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate C4 boundary id "%s".', $id));
        }
        if (null !== $label) {
            $this->assertText($label, \sprintf('C4 boundary "%s" label', $id));
        }

        $this->boundaries[$id] = new C4Boundary($id, new Label($label ?? $id));
        $this->boundaryOrder[] = $id;
        $this->currentBoundary = $id;

        return $this;
    }

    public function endBoundary(): self
    {
        if (null === $this->currentBoundary) {
            throw new InvalidArgumentException('Cannot end a C4 boundary before starting one.');
        }

        $this->currentBoundary = null;

        return $this;
    }

    public function element(C4ElementKind $kind, string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        $this->assertIdentifier($id, 'C4 element id');
        if (isset($this->elements[$id])) {
            throw new InvalidArgumentException(\sprintf('Duplicate C4 element id "%s".', $id));
        }
        if (null !== $label) {
            $this->assertText($label, \sprintf('C4 element "%s" label', $id));
        }
        if (null !== $technology) {
            $this->assertText($technology, \sprintf('C4 element "%s" technology', $id));
        }
        if (null !== $description) {
            $this->assertText($description, \sprintf('C4 element "%s" description', $id));
        }

        $resolvedBoundary = $boundaryId ?? $this->currentBoundary;
        if (null !== $resolvedBoundary) {
            $this->assertKnownBoundary($resolvedBoundary);
        }

        $this->elements[$id] = new C4Element(
            $id,
            $kind,
            new Label($label ?? $id),
            null !== $technology ? new Label($technology) : null,
            null !== $description ? new Label($description) : null,
            $resolvedBoundary,
        );
        $this->elementOrder[] = $id;

        return $this;
    }

    public function person(string $id, ?string $label = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::Person, $id, $label, null, $description, $boundaryId);
    }

    public function externalPerson(string $id, ?string $label = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::PersonExternal, $id, $label, null, $description, $boundaryId);
    }

    public function system(string $id, ?string $label = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::System, $id, $label, null, $description, $boundaryId);
    }

    public function externalSystem(string $id, ?string $label = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::SystemExternal, $id, $label, null, $description, $boundaryId);
    }

    public function container(string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::Container, $id, $label, $technology, $description, $boundaryId);
    }

    public function externalContainer(string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::ContainerExternal, $id, $label, $technology, $description, $boundaryId);
    }

    public function database(string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::ContainerDatabase, $id, $label, $technology, $description, $boundaryId);
    }

    public function component(string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::Component, $id, $label, $technology, $description, $boundaryId);
    }

    public function externalComponent(string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::ComponentExternal, $id, $label, $technology, $description, $boundaryId);
    }

    public function componentDatabase(string $id, ?string $label = null, ?string $technology = null, ?string $description = null, ?string $boundaryId = null): self
    {
        return $this->element(C4ElementKind::ComponentDatabase, $id, $label, $technology, $description, $boundaryId);
    }

    public function relationship(string $from, string $to, string $label, ?string $technology = null): self
    {
        $this->assertKnownElement($from);
        $this->assertKnownElement($to);
        $this->assertText($label, \sprintf('C4 relationship "%s" to "%s" label', $from, $to));
        if (null !== $technology) {
            $this->assertText($technology, \sprintf('C4 relationship "%s" to "%s" technology', $from, $to));
        }

        $this->relationships[] = new C4Relationship($from, $to, new Label($label), null !== $technology ? new Label($technology) : null);

        return $this;
    }

    public function build(): C4Diagram
    {
        $boundaries = [];
        foreach ($this->boundaryOrder as $id) {
            $boundaries[] = $this->boundaries[$id];
        }

        $elements = [];
        foreach ($this->elementOrder as $id) {
            $elements[] = $this->elements[$id];
        }

        return new C4Diagram($this->view, $boundaries, $elements, $this->relationships, $this->title);
    }

    private function assertKnownBoundary(string $id): void
    {
        if (!isset($this->boundaries[$id])) {
            throw new InvalidArgumentException(\sprintf('C4 element references unknown boundary "%s".', $id));
        }
    }

    private function assertKnownElement(string $id): void
    {
        if (!isset($this->elements[$id])) {
            throw new InvalidArgumentException(\sprintf('C4 relationship references unknown element "%s".', $id));
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
