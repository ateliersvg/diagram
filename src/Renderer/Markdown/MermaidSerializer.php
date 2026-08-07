<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;

/**
 * Internal adapter from a model class to a typed concrete serializer.
 *
 * @template T of DiagramModel
 *
 * @internal
 */
final readonly class MermaidSerializer implements MermaidDiagramSerializerInterface
{
    /**
     * @param class-string<T>     $modelClass
     * @param \Closure(T): string $serialize
     */
    public function __construct(
        private string $modelClass,
        private \Closure $serialize,
    ) {
    }

    public function modelClass(): string
    {
        return $this->modelClass;
    }

    public function serialize(DiagramModel $diagram): string
    {
        if (!$diagram instanceof $this->modelClass) {
            throw new InvalidArgumentException(\sprintf('Diagram model "%s" cannot be rendered by this Mermaid serializer.', $diagram::class));
        }

        return ($this->serialize)($diagram);
    }
}
