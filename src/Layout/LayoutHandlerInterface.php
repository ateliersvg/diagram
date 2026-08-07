<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout;

use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Theme\Theme;

/**
 * @internal
 */
interface LayoutHandlerInterface
{
    /**
     * @return class-string<DiagramModel>
     */
    public function modelClass(): string;

    public function layout(DiagramModel $diagram, Theme $theme): Scene;
}
