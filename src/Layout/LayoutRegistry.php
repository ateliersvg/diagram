<?php

declare(strict_types=1);

namespace Atelier\Diagram\Layout;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\ClassDiagram\ClassDiagram as ClassDiagramModel;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Layout\Support\ThemeTextMeasurer;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Venn\VennDiagram;

/**
 * Registry of model-to-layout dispatchers.
 *
 * @internal
 */
final readonly class LayoutRegistry
{
    /**
     * @var array<class-string<DiagramModel>, LayoutHandlerInterface>
     */
    private array $handlers;

    /**
     * @param list<LayoutHandlerInterface> $handlers
     */
    public function __construct(array $handlers)
    {
        $indexed = [];
        foreach ($handlers as $handler) {
            if (isset($indexed[$handler->modelClass()])) {
                throw new InvalidArgumentException(\sprintf('Duplicate layout handler model class "%s" in registry.', $handler->modelClass()));
            }

            $indexed[$handler->modelClass()] = $handler;
        }

        $this->handlers = $indexed;
    }

    public static function default(): self
    {
        static $default = null;

        if (!$default instanceof self) {
            $default = self::buildDefault();
        }

        return $default;
    }

    private static function buildDefault(): self
    {
        return new self([
            new LayoutHandler(StateDiagram::class, static fn (StateDiagram $diagram, Theme $theme): Scene => (new State\StateLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(VennDiagram::class, static fn (VennDiagram $diagram, Theme $theme): Scene => (new Venn\VennLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(GitGraph::class, static fn (GitGraph $graph, Theme $theme): Scene => (new Git\GitLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($graph, $theme)),
            new LayoutHandler(SequenceDiagram::class, static fn (SequenceDiagram $diagram, Theme $theme): Scene => (new Sequence\SequenceLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(Flowchart::class, static fn (Flowchart $flowchart, Theme $theme): Scene => (new Flow\FlowchartLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($flowchart, $theme)),
            new LayoutHandler(ClassDiagramModel::class, static fn (ClassDiagramModel $diagram, Theme $theme): Scene => (new ClassDiagram\ClassDiagramLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(ErDiagram::class, static fn (ErDiagram $diagram, Theme $theme): Scene => (new Er\ErLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(TimelineDiagram::class, static fn (TimelineDiagram $diagram, Theme $theme): Scene => (new Timeline\TimelineLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(JourneyDiagram::class, static fn (JourneyDiagram $diagram, Theme $theme): Scene => (new Journey\JourneyLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(MindmapDiagram::class, static fn (MindmapDiagram $diagram, Theme $theme): Scene => (new Mindmap\MindmapLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(RequirementDiagram::class, static fn (RequirementDiagram $diagram, Theme $theme): Scene => (new Requirement\RequirementLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(KanbanDiagram::class, static fn (KanbanDiagram $diagram, Theme $theme): Scene => (new Kanban\KanbanLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(BlockDiagram::class, static fn (BlockDiagram $diagram, Theme $theme): Scene => (new Block\BlockLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(ArchitectureDiagram::class, static fn (ArchitectureDiagram $diagram, Theme $theme): Scene => (new Architecture\ArchitectureLayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
            new LayoutHandler(C4Diagram::class, static fn (C4Diagram $diagram, Theme $theme): Scene => (new C4\C4LayoutEngine(ThemeTextMeasurer::for($theme)))->layout($diagram, $theme)),
        ]);
    }

    public function layout(DiagramModel $diagram, Theme $theme): Scene
    {
        $handler = $this->handlers[$diagram::class] ?? null;
        if (null === $handler) {
            throw new InvalidArgumentException(\sprintf('Diagram model "%s" cannot be laid out: no layout handler is registered.', $diagram::class));
        }

        return $handler->layout($diagram, $theme);
    }

    /**
     * @return list<class-string<DiagramModel>>
     */
    public function modelClasses(): array
    {
        return array_keys($this->handlers);
    }
}
