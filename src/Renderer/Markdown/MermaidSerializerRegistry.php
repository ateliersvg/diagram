<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Timeline\TimelineDiagram;

/**
 * Registry of model-to-Mermaid serializers.
 *
 * @internal
 */
final readonly class MermaidSerializerRegistry
{
    /**
     * @var array<class-string<DiagramModel>, MermaidDiagramSerializerInterface>
     */
    private array $serializers;

    /**
     * @param list<MermaidDiagramSerializerInterface> $serializers
     */
    public function __construct(array $serializers)
    {
        $indexed = [];
        foreach ($serializers as $serializer) {
            if (isset($indexed[$serializer->modelClass()])) {
                throw new InvalidArgumentException(\sprintf('Duplicate Mermaid serializer model class "%s" in registry.', $serializer->modelClass()));
            }
            $indexed[$serializer->modelClass()] = $serializer;
        }

        $this->serializers = $indexed;
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
            new MermaidSerializer(StateDiagram::class, static fn (StateDiagram $diagram): string => (new StateDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(GitGraph::class, static fn (GitGraph $diagram): string => (new GitGraphSerializer())->serialize($diagram)),
            new MermaidSerializer(SequenceDiagram::class, static fn (SequenceDiagram $diagram): string => (new SequenceDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(Flowchart::class, static fn (Flowchart $diagram): string => (new FlowchartSerializer())->serialize($diagram)),
            new MermaidSerializer(ClassDiagram::class, static fn (ClassDiagram $diagram): string => (new ClassDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(ErDiagram::class, static fn (ErDiagram $diagram): string => (new ErDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(TimelineDiagram::class, static fn (TimelineDiagram $diagram): string => (new TimelineDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(JourneyDiagram::class, static fn (JourneyDiagram $diagram): string => (new JourneyDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(MindmapDiagram::class, static fn (MindmapDiagram $diagram): string => (new MindmapDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(RequirementDiagram::class, static fn (RequirementDiagram $diagram): string => (new RequirementDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(KanbanDiagram::class, static fn (KanbanDiagram $diagram): string => (new KanbanDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(BlockDiagram::class, static fn (BlockDiagram $diagram): string => (new BlockDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(ArchitectureDiagram::class, static fn (ArchitectureDiagram $diagram): string => (new ArchitectureDiagramSerializer())->serialize($diagram)),
            new MermaidSerializer(C4Diagram::class, static fn (C4Diagram $diagram): string => (new C4DiagramSerializer())->serialize($diagram)),
        ]);
    }

    public function serialize(DiagramModel $diagram): string
    {
        $serializer = $this->serializers[$diagram::class] ?? null;
        if (null === $serializer) {
            throw new InvalidArgumentException(\sprintf('Diagram model "%s" cannot be rendered to Mermaid: no Mermaid serializer is registered.', $diagram::class));
        }

        return $serializer->serialize($diagram);
    }

    /**
     * @return list<class-string<DiagramModel>>
     */
    public function modelClasses(): array
    {
        return array_keys($this->serializers);
    }
}
