<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Model\DiagramModel;

/**
 * @internal
 */
interface MermaidDiagramSerializerInterface
{
    /**
     * @return class-string<DiagramModel>
     */
    public function modelClass(): string;

    public function serialize(DiagramModel $diagram): string;
}
