<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Model;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Title;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Title::class)]
final class TitleTest extends TestCase
{
    public function testExposesText(): void
    {
        $title = new Title('Order lifecycle');

        $this->assertSame('Order lifecycle', $title->text);
    }

    public function testRejectsEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title text must be a non-empty string.');

        new Title('');
    }

    public function testRejectsWhitespaceOnlyText(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Title("  \t ");
    }
}
