<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Markdown;

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
use Atelier\Diagram\Renderer\Markdown\MermaidSerializer;
use Atelier\Diagram\Renderer\Markdown\MermaidSerializerRegistry;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Timeline\TimelineDiagram;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidSerializerRegistry::class)]
#[CoversClass(MermaidSerializer::class)]
final class MermaidSerializerRegistryTest extends TestCase
{
    public function testDefaultRegistryIsCached(): void
    {
        $this->assertSame(MermaidSerializerRegistry::default(), MermaidSerializerRegistry::default());
    }

    public function testDefaultModelClassesAreUniqueAndComplete(): void
    {
        $classes = MermaidSerializerRegistry::default()->modelClasses();

        $this->assertCount(\count(array_unique($classes)), $classes);
        $this->assertContains(StateDiagram::class, $classes);
        $this->assertContains(GitGraph::class, $classes);
        $this->assertContains(SequenceDiagram::class, $classes);
        $this->assertContains(Flowchart::class, $classes);
        $this->assertContains(ClassDiagram::class, $classes);
        $this->assertContains(ErDiagram::class, $classes);
        $this->assertContains(TimelineDiagram::class, $classes);
        $this->assertContains(JourneyDiagram::class, $classes);
        $this->assertContains(MindmapDiagram::class, $classes);
        $this->assertContains(RequirementDiagram::class, $classes);
        $this->assertContains(KanbanDiagram::class, $classes);
        $this->assertContains(BlockDiagram::class, $classes);
        $this->assertContains(ArchitectureDiagram::class, $classes);
        $this->assertContains(C4Diagram::class, $classes);
    }

    public function testUnregisteredModelThrowsActionableError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no Mermaid serializer is registered');

        MermaidSerializerRegistry::default()->serialize(new class implements DiagramModel {
        });
    }

    public function testSerializerAdapterRejectsWrongModel(): void
    {
        $serializer = new MermaidSerializer(StateDiagram::class, static fn (StateDiagram $_diagram): string => 'stateDiagram-v2');

        $this->assertSame(StateDiagram::class, $serializer->modelClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be rendered by this Mermaid serializer');

        $serializer->serialize(new class implements DiagramModel {
        });
    }

    public function testDuplicateModelClassIsRejectedAtConstructionTime(): void
    {
        // Two serializers advertising the same model class. A second concrete
        // MermaidSerializer (not an anonymous MermaidDiagramSerializerInterface)
        // is used deliberately: under pcov, compiling an anonymous class that
        // implements MermaidDiagramSerializerInterface in this test file zeroes
        // the recorded coverage of the registry it constructs.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate Mermaid serializer model class "Atelier\\Diagram\\State\\StateDiagram" in registry.');

        new MermaidSerializerRegistry([
            new MermaidSerializer(StateDiagram::class, static fn (StateDiagram $_diagram): string => 'stateDiagram-v2'),
            new MermaidSerializer(StateDiagram::class, static fn (StateDiagram $_diagram): string => 'stateDiagram-v2'),
        ]);
    }
}
