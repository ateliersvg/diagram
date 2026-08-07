<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Theme\Theme;

/**
 * Internal adapter from a model class to a typed concrete layout engine.
 *
 * @template T of DiagramModel
 *
 * @internal
 */
final readonly class LayoutHandler implements LayoutHandlerInterface
{
    /**
     * @param class-string<T>           $modelClass
     * @param \Closure(T, Theme): Scene $layout
     */
    public function __construct(
        private string $modelClass,
        private \Closure $layout,
    ) {
    }

    public function modelClass(): string
    {
        return $this->modelClass;
    }

    public function layout(DiagramModel $diagram, Theme $theme): Scene
    {
        if (!$diagram instanceof $this->modelClass) {
            throw new InvalidArgumentException(\sprintf('Diagram model "%s" cannot be laid out by this handler.', $diagram::class));
        }

        return ($this->layout)($diagram, $theme);
    }
}
