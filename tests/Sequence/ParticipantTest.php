<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Sequence\Participant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Participant::class)]
final class ParticipantTest extends TestCase
{
    public function testConstructsWithIdAndLabel(): void
    {
        $participant = new Participant('User', 'Customer');

        $this->assertSame('User', $participant->id);
        $this->assertSame('Customer', $participant->label);
    }

    public function testRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('participant id must be non-empty');

        new Participant('  ', 'Customer');
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('participant label must be non-empty');

        new Participant('User', '  ');
    }
}
