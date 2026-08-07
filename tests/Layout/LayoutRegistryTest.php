<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\Diagram;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Layout\LayoutHandler;
use Atelier\Diagram\Layout\LayoutRegistry;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Venn\VennDiagram;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LayoutRegistry::class)]
#[CoversClass(LayoutHandler::class)]
final class LayoutRegistryTest extends TestCase
{
    public function testRegistryDispatchesToMatchingHandler(): void
    {
        $model = new class implements DiagramModel {};
        $registry = new LayoutRegistry([
            new LayoutHandler($model::class, static fn (DiagramModel $_diagram, Theme $_theme): Scene => new Scene(12.0, 34.0)),
        ]);

        $scene = $registry->layout($model, Theme::default());

        $this->assertSame(12.0, $scene->width);
        $this->assertSame(34.0, $scene->height);
    }

    public function testDefaultRegistryIsCached(): void
    {
        $this->assertSame(LayoutRegistry::default(), LayoutRegistry::default());
    }

    public function testDefaultModelClassesAreUniqueAndComplete(): void
    {
        $classes = LayoutRegistry::default()->modelClasses();

        $this->assertCount(\count(array_unique($classes)), $classes);
        $this->assertContains(StateDiagram::class, $classes);
        $this->assertContains(VennDiagram::class, $classes);
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

    public function testDefaultRegistryCanLayoutEveryRegisteredModel(): void
    {
        $registry = self::buildFreshDefaultRegistry();

        foreach ($this->representativeModels() as $model) {
            $scene = $registry->layout($model, Theme::default());

            $this->assertGreaterThan(0.0, $scene->width, $model::class);
            $this->assertGreaterThan(0.0, $scene->height, $model::class);
        }
    }

    public function testUnregisteredModelThrowsActionableError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no layout handler is registered');

        LayoutRegistry::default()->layout(new class implements DiagramModel {
        }, Theme::default());
    }

    public function testDuplicateModelClassIsRejectedAtConstructionTime(): void
    {
        $handler = new LayoutHandler(StateDiagram::class, static fn (StateDiagram $_diagram, Theme $_theme): Scene => new Scene());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate layout handler model class "Atelier\\Diagram\\State\\StateDiagram" in registry.');

        new LayoutRegistry([
            $handler,
            new LayoutHandler(StateDiagram::class, static fn (StateDiagram $_diagram, Theme $_theme): Scene => new Scene(1.0, 1.0)),
        ]);
    }

    public function testHandlerRejectsModelOfDifferentClass(): void
    {
        $handler = new LayoutHandler(StateDiagram::class, static fn (StateDiagram $_diagram, Theme $_theme): Scene => new Scene());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be laid out by this handler');

        $handler->layout(new class implements DiagramModel {
        }, Theme::default());
    }

    private static function buildFreshDefaultRegistry(): LayoutRegistry
    {
        $method = new \ReflectionMethod(LayoutRegistry::class, 'buildDefault');

        return $method->invoke(null);
    }

    /**
     * @return list<DiagramModel>
     */
    private function representativeModels(): array
    {
        return [
            Diagram::state()
                ->initial('Draft')
                ->transition('Draft', 'Done', 'finish')
                ->final('Done')
                ->build(),
            Diagram::venn()
                ->set('Cats')
                ->set('Dogs')
                ->regionLabel('AB', 'Pets')
                ->build(),
            Diagram::git()
                ->commit()
                ->branch('feature')
                ->commit()
                ->checkout('main')
                ->merge('feature')
                ->build(),
            Diagram::sequence()
                ->participant('Client')
                ->participant('Server')
                ->message('Client', 'Server', 'Request')
                ->build(),
            Diagram::flowchart()
                ->node('A', 'Start')
                ->edge('A', 'B', 'go')
                ->node('B', 'Done')
                ->build(),
            Diagram::classDiagram()
                ->class('User')
                ->member('User', '+email: string')
                ->build(),
            Diagram::er()
                ->attribute('CUSTOMER', 'string', 'email')
                ->attribute('ORDER', 'decimal', 'total')
                ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
                ->build(),
            Diagram::timeline()
                ->section('Discovery')
                ->event('Research complete', '2026-01')
                ->build(),
            Diagram::journey()
                ->section('Browse')
                ->task('Open product page', 5, ['Customer'])
                ->build(),
            Diagram::mindmap()
                ->root('Atelier', 'root')
                ->child('root', 'Layout')
                ->build(),
            Diagram::requirement()
                ->requirement('checkout', ['id' => 'REQ-1'])
                ->element('cart', ['type' => 'component'])
                ->relationship('cart', 'satisfies', 'checkout')
                ->build(),
            Diagram::kanban()
                ->column('todo', 'Todo')
                ->card('todo', 'REQ-1', 'Write parser')
                ->build(),
            Diagram::block()
                ->block('Solver', 'LayoutSolver')
                ->block('Grid', 'Grid')
                ->relationship('Solver', 'Grid', 'solves')
                ->build(),
            Diagram::architecture()
                ->group('Web', 'Web tier')
                ->component('App', 'Frontend app', 'Web')
                ->component('Api', 'Checkout API', 'Web')
                ->relationship('App', 'Api', 'calls')
                ->build(),
            Diagram::c4()
                ->containerView()
                ->person('buyer', 'Buyer')
                ->boundary('shop', 'Shop Platform')
                    ->container('web', 'Web App', 'Symfony')
                ->endBoundary()
                ->relationship('buyer', 'web', 'uses')
                ->build(),
        ];
    }
}
