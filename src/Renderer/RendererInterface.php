<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer;

use Atelier\Diagram\Scene\Scene;

/**
 * Renders a Scene to a string in some output format.
 */
interface RendererInterface
{
    /**
     * Renders the scene and returns the serialized output.
     */
    public function render(Scene $scene): string;
}
