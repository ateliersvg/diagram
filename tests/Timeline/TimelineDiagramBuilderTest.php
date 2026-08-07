<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Timeline;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;
use Atelier\Diagram\Timeline\TimelineEvent;
use Atelier\Diagram\Timeline\TimelineSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimelineDiagramBuilder::class)]
#[CoversClass(TimelineDiagram::class)]
#[CoversClass(TimelineSection::class)]
#[CoversClass(TimelineEvent::class)]
final class TimelineDiagramBuilderTest extends TestCase
{
    public function testBuildsSectionsAndEvents(): void
    {
        $diagram = (new TimelineDiagramBuilder())
            ->title('Product launch')
            ->section('Discovery')
            ->event('Research complete', '2026-01')
            ->event('Prototype review', '2026-02')
            ->section('Build')
            ->event('Private beta', '2026-04')
            ->build();

        $this->assertSame('Product launch', $diagram->title?->text);
        $this->assertCount(2, $diagram->sections);
        $this->assertSame('Discovery', $diagram->sections[0]->title);
        $this->assertSame('Research complete', $diagram->sections[0]->events[0]->label);
        $this->assertSame('2026-04', $diagram->sections[1]->events[0]->date);
    }

    public function testEventMustBelongToSection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline event must belong to a section');

        (new TimelineDiagramBuilder())->event('Research complete', '2026-01');
    }

    public function testSectionMustContainEvent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain at least one event');

        (new TimelineDiagramBuilder())->section('Discovery')->build();
    }

    public function testBuilderRejectsEmptySectionTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline section title must be non-empty');

        (new TimelineDiagramBuilder())->section('  ');
    }

    public function testBuilderRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline diagram must contain at least one section');

        (new TimelineDiagramBuilder())->build();
    }

    public function testNonEmptySectionsGuardRejectsEmptyList(): void
    {
        $method = new \ReflectionMethod(TimelineDiagramBuilder::class, 'nonEmptySections');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline diagram must contain at least one section');

        $method->invoke(new TimelineDiagramBuilder(), []);
    }

    public function testEventAcceptsValidConstruction(): void
    {
        $event = new TimelineEvent('Private beta', '2026-04');

        $this->assertSame('Private beta', $event->label);
        $this->assertSame('2026-04', $event->date);
    }

    public function testEventRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline event label must be non-empty');

        new TimelineEvent('  ', '2026-04');
    }

    public function testEventRejectsEmptyDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline event date must be non-empty');

        new TimelineEvent('Private beta', '  ');
    }

    public function testSectionAcceptsValidConstruction(): void
    {
        $section = new TimelineSection('Discovery', [new TimelineEvent('Private beta', '2026-04')]);

        $this->assertSame('Discovery', $section->title);
        $this->assertCount(1, $section->events);
    }

    public function testSectionRejectsEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline section title must be non-empty');

        new TimelineSection('  ', []);
    }

    public function testSectionRejectsEmptyEvents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain at least one event');

        new TimelineSection('Discovery', []);
    }

    public function testDiagramAcceptsValidConstruction(): void
    {
        $diagram = new TimelineDiagram([
            new TimelineSection('Discovery', [new TimelineEvent('Private beta', '2026-04')]),
        ]);

        $this->assertCount(1, $diagram->sections);
        $this->assertNull($diagram->title);
    }

    public function testDiagramRejectsEmptySections(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeline diagram must contain at least one section');

        new TimelineDiagram([]);
    }
}
