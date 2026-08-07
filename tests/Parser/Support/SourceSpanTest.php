<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Support\SourceSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceSpan::class)]
final class SourceSpanTest extends TestCase
{
    public function testExposesPositiveSpanBounds(): void
    {
        $span = new SourceSpan(2, 1, 3, 5);

        $this->assertSame(2, $span->startLine);
        $this->assertSame(1, $span->startColumn);
        $this->assertSame(3, $span->endLine);
        $this->assertSame(5, $span->endColumn);
    }

    public function testRejectsNonPositiveLines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source span lines must be positive.');

        new SourceSpan(0, 1, 1, 1);
    }

    public function testRejectsNonPositiveColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source span columns must be positive.');

        new SourceSpan(1, 0, 1, 1);
    }
}
