<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\State;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\State\Transition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Transition::class)]
final class TransitionTest extends TestCase
{
    public function testConstructsWithEndpointsAndLabel(): void
    {
        $label = new Label('go');
        $transition = new Transition('idle', 'running', $label);

        $this->assertSame('idle', $transition->from);
        $this->assertSame('running', $transition->to);
        $this->assertSame($label, $transition->label);
    }

    public function testRejectsEmptyFromEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transition endpoints must be non-empty state ids');

        new Transition('  ', 'running');
    }

    public function testRejectsEmptyToEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transition endpoints must be non-empty state ids');

        new Transition('idle', '  ');
    }
}
