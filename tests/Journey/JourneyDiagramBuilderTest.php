<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Journey;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Journey\JourneyActor;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneyDiagramBuilder;
use Atelier\Diagram\Journey\JourneySection;
use Atelier\Diagram\Journey\JourneyTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JourneyDiagramBuilder::class)]
#[CoversClass(JourneyDiagram::class)]
#[CoversClass(JourneySection::class)]
#[CoversClass(JourneyTask::class)]
#[CoversClass(JourneyActor::class)]
final class JourneyDiagramBuilderTest extends TestCase
{
    public function testBuildsSectionsTasksScoresAndActors(): void
    {
        $diagram = (new JourneyDiagramBuilder())
            ->title('Checkout experience')
            ->section('Browse')
            ->task('Open product page', 5, ['Customer'])
            ->section('Payment')
            ->task('Enter card', 3, ['Customer', 'PSP'])
            ->build();

        $this->assertSame('Checkout experience', $diagram->title?->text);
        $this->assertSame('Browse', $diagram->sections[0]->title);
        $this->assertSame('Open product page', $diagram->sections[0]->tasks[0]->text);
        $this->assertSame(5, $diagram->sections[0]->tasks[0]->score);
        $this->assertSame('PSP', $diagram->sections[1]->tasks[0]->actors[1]->name);
    }

    public function testRejectsTaskBeforeSection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey task must belong to a section');

        (new JourneyDiagramBuilder())->task('Open product page', 5, ['Customer']);
    }

    public function testRejectsInvalidScore(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey task score must be between 1 and 5');

        (new JourneyDiagramBuilder())
            ->section('Browse')
            ->task('Open product page', 6, ['Customer']);
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey diagram must contain at least one section');

        (new JourneyDiagramBuilder())->build();
    }

    public function testRejectsEmptySectionTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey section title must be non-empty');

        (new JourneyDiagramBuilder())->section('  ');
    }

    public function testRejectsSectionWithoutTasks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain at least one task');

        (new JourneyDiagramBuilder())->section('Browse')->build();
    }

    public function testNonEmptySectionsGuardRejectsEmptyList(): void
    {
        $method = new \ReflectionMethod(JourneyDiagramBuilder::class, 'nonEmptySections');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey diagram must contain at least one section');

        $method->invoke(new JourneyDiagramBuilder(), []);
    }

    public function testActorAcceptsValidName(): void
    {
        $actor = new JourneyActor('Customer');

        $this->assertSame('Customer', $actor->name);
    }

    public function testActorRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey actor name must be non-empty');

        new JourneyActor('  ');
    }

    public function testTaskAcceptsValidConstruction(): void
    {
        $task = new JourneyTask('Open page', 3, [new JourneyActor('Customer')]);

        $this->assertSame('Open page', $task->text);
        $this->assertSame(3, $task->score);
        $this->assertSame('Customer', $task->actors[0]->name);
    }

    public function testTaskRejectsEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey task text must be non-empty');

        new JourneyTask('  ', 3, [new JourneyActor('Customer')]);
    }

    public function testTaskRejectsEmptyActors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey task must declare at least one actor');

        new JourneyTask('Open page', 3, []);
    }

    public function testSectionAcceptsValidConstruction(): void
    {
        $section = new JourneySection('Browse', [new JourneyTask('Open page', 3, [new JourneyActor('Customer')])]);

        $this->assertSame('Browse', $section->title);
        $this->assertCount(1, $section->tasks);
    }

    public function testSectionRejectsEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey section title must be non-empty');

        new JourneySection('  ', []);
    }

    public function testSectionRejectsEmptyTasks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain at least one task');

        new JourneySection('Browse', []);
    }

    public function testDiagramAcceptsValidConstruction(): void
    {
        $diagram = new JourneyDiagram([
            new JourneySection('Browse', [new JourneyTask('Open page', 3, [new JourneyActor('Customer')])]),
        ]);

        $this->assertCount(1, $diagram->sections);
        $this->assertNull($diagram->title);
    }

    public function testDiagramRejectsEmptySections(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journey diagram must contain at least one section');

        new JourneyDiagram([]);
    }
}
