<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\State;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\State\State;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(State::class)]
final class StateTest extends TestCase
{
    public function testLabelDefaultsToId(): void
    {
        $state = new State('idle');

        $this->assertSame('idle', $state->id);
        $this->assertSame('idle', $state->label);
    }

    public function testKeepsExplicitLabel(): void
    {
        $state = new State('idle', 'Idle');

        $this->assertSame('idle', $state->id);
        $this->assertSame('Idle', $state->label);
    }

    public function testRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('State id must be a non-empty string');

        new State('  ');
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('label must be a non-empty string');

        new State('idle', '  ');
    }
}
