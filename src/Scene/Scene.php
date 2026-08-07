<?php

declare(strict_types=1);

namespace Atelier\Diagram\Scene;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Renderer-agnostic geometric IR: a sized canvas holding positioned,
 * styled primitives. Produced by layout engines, consumed by renderers.
 */
final readonly class Scene
{
    /**
     * @param float                  $width             canvas width, px
     * @param float                  $height            canvas height, px
     * @param string|null            $backgroundColor   background color, null for transparent
     * @param list<NodeInterface>    $nodes             nodes in paint order
     * @param string|null            $title             accessible scene title
     * @param string|null            $description       accessible scene description
     * @param BackgroundPattern|null $backgroundPattern optional decoration painted behind the nodes
     */
    public function __construct(
        public float $width,
        public float $height,
        public ?string $backgroundColor = null,
        public array $nodes = [],
        public ?string $title = null,
        public ?string $description = null,
        public ?BackgroundPattern $backgroundPattern = null,
    ) {
        if ($width <= 0.0 || $height <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Scene dimensions must be positive, got %s x %s.', $width, $height));
        }
        if (null !== $title && '' === trim($title)) {
            throw new InvalidArgumentException('Scene title must not be empty when provided.');
        }
        if (null !== $description && '' === trim($description)) {
            throw new InvalidArgumentException('Scene description must not be empty when provided.');
        }
    }

    /**
     * Returns a copy of this scene carrying the given background pattern.
     */
    public function withBackgroundPattern(?BackgroundPattern $backgroundPattern): self
    {
        return new self(
            $this->width,
            $this->height,
            $this->backgroundColor,
            $this->nodes,
            $this->title,
            $this->description,
            $backgroundPattern,
        );
    }
}
