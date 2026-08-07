<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\State;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\State\State;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\StateDiagramBuilder;
use Atelier\Diagram\State\Transition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateDiagramBuilder::class)]
#[CoversClass(StateDiagram::class)]
#[CoversClass(State::class)]
#[CoversClass(Transition::class)]
final class StateDiagramBuilderTest extends TestCase
{
    public function testBuildsStatesTransitionsTitleAndDirection(): void
    {
        $diagram = (new StateDiagramBuilder())
            ->title('Order lifecycle')
            ->direction(Direction::LeftToRight)
            ->state('Draft', 'Draft order')
            ->initial('Draft')
            ->transition('Draft', 'Paid', 'pay')
            ->final('Paid')
            ->build();

        $this->assertSame('Order lifecycle', $diagram->title?->text);
        $this->assertSame(Direction::LeftToRight, $diagram->direction);
        $this->assertSame('Draft', $diagram->states[0]->id);
        $this->assertSame('Draft order', $diagram->states[0]->label);
        $this->assertSame('Paid', $diagram->states[1]->id);
        $this->assertSame(StateDiagram::INITIAL, $diagram->transitions[0]->from);
        $this->assertSame('Draft', $diagram->transitions[0]->to);
        $this->assertSame('pay', $diagram->transitions[1]->label?->text);
        $this->assertSame(StateDiagram::FINAL, $diagram->transitions[2]->to);
    }

    public function testTransitionAutoDeclaresUnknownStatesWithIdAsLabel(): void
    {
        $diagram = (new StateDiagramBuilder())
            ->transition('A', 'B')
            ->build();

        $this->assertCount(2, $diagram->states);
        $this->assertSame('A', $diagram->states[0]->id);
        $this->assertSame('A', $diagram->states[0]->label);
        $this->assertSame('B', $diagram->states[1]->id);
    }

    public function testDeclaringStateWithLabelOverridesPreviousLabel(): void
    {
        $diagram = (new StateDiagramBuilder())
            ->state('A')
            ->state('A', 'Alpha')
            ->build();

        $this->assertCount(1, $diagram->states);
        $this->assertSame('Alpha', $diagram->states[0]->label);
    }

    public function testRejectsDeclaringReservedInitialPseudoState(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('reserved pseudo-state id');

        (new StateDiagramBuilder())->state(StateDiagram::INITIAL);
    }

    public function testRejectsTransitionStartingFromFinalPseudoState(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('cannot start from the final pseudo-state');

        (new StateDiagramBuilder())->transition(StateDiagram::FINAL, 'A');
    }

    public function testRejectsTransitionEndingAtInitialPseudoState(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('cannot end at the initial pseudo-state');

        (new StateDiagramBuilder())->transition('A', StateDiagram::INITIAL);
    }

    public function testRejectsEmptyDiagram(): void
    {
        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('must declare at least one state');

        (new StateDiagramBuilder())->build();
    }
}
