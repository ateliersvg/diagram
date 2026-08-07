<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Sequence\SequenceActivation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceActivation::class)]
final class SequenceActivationTest extends TestCase
{
    public function testConstructsWithParticipantAndRange(): void
    {
        $activation = new SequenceActivation('Server', 1, 3);

        $this->assertSame('Server', $activation->participant);
        $this->assertSame(1, $activation->firstMessageIndex);
        $this->assertSame(3, $activation->lastMessageIndex);
    }

    public function testRejectsEmptyParticipant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('activation participant must be non-empty');

        new SequenceActivation('  ', 0, 1);
    }

    public function testRejectsInvalidRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('activation message range is invalid');

        new SequenceActivation('Server', 3, 1);
    }
}
