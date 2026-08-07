<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Sequence\Message;
use Atelier\Diagram\Sequence\MessageArrow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testConstructsWithEndpointsAndLabel(): void
    {
        $message = new Message('A', 'B', 'Call', MessageArrow::Dashed);

        $this->assertSame('A', $message->from);
        $this->assertSame('B', $message->to);
        $this->assertSame('Call', $message->label);
        $this->assertSame(MessageArrow::Dashed, $message->arrow);
    }

    public function testRejectsEmptyFromEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('endpoints must be non-empty');

        new Message('  ', 'B', 'Call');
    }

    public function testRejectsEmptyToEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('endpoints must be non-empty');

        new Message('A', '  ', 'Call');
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message label must be non-empty');

        new Message('A', 'B', '  ');
    }
}
